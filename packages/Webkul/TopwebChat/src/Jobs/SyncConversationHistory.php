<?php

namespace Webkul\TopwebChat\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\TopwebChat\Exceptions\ProviderRequestException;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;

class SyncConversationHistory implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public function __construct(
        public int $conversationId,
        public bool $backfill = false,
        public ?string $from = null,
        public ?string $to = null
    ) {}

    public function handle(MessagingProvider $provider): void
    {
        try {
            $acquired = Cache::lock(
                "topweb-chat:history:{$this->conversationId}",
                55
            )->get(function () use ($provider) {
                $conversation = Conversation::query()
                    ->with('instance')
                    ->findOrFail($this->conversationId);

                if ($this->backfill) {
                    $this->syncBackfill($provider, $conversation);
                } else {
                    $this->syncRecent($provider, $conversation);
                }

                return true;
            });
        } catch (ProviderRequestException $exception) {
            if ($exception->statusCode === 429) {
                $this->release($exception->retryAfter);

                return;
            }

            if ($exception->statusCode !== 501) {
                throw $exception;
            }

            $conversation = Conversation::query()->find($this->conversationId);

            if ($conversation) {
                Cache::put(
                    "topweb-chat:history-unavailable:{$conversation->instance_id}",
                    true,
                    now()->addMinutes(5)
                );
            }

            Log::warning('OpenWA history synchronization is unavailable.', [
                'conversation_id' => $this->conversationId,
                'instance_id' => $conversation?->instance_id,
                'status_code' => $exception->statusCode,
            ]);

            return;
        }

        if (! $acquired) {
            $this->release(5);
        }
    }

    private function syncRecent(
        MessagingProvider $provider,
        Conversation $conversation
    ): void {
        $from = $this->from;

        if (! $from) {
            $latest = $conversation->messages()
                ->whereNotNull('sent_at')
                ->max('sent_at');
            $from = $latest
                ? Carbon::parse($latest)->subSecond()->toIso8601String()
                : null;
        }

        $history = $provider->history(
            $conversation->instance,
            $conversation->remote_jid,
            100,
            $from ? Carbon::parse($from) : null,
            $this->to ? Carbon::parse($this->to) : null
        );

        Cache::forget("topweb-chat:history-unavailable:{$conversation->instance_id}");
        Cache::forget("topweb-chat:provider-unavailable:{$conversation->instance_id}");

        $this->persist(
            $conversation,
            array_reverse($history['messages']),
            true
        );

        $this->continueRecentSync($history, $from);
    }

    private function syncBackfill(
        MessagingProvider $provider,
        Conversation $conversation
    ): void {
        if ($conversation->history_backfilled_at) {
            return;
        }

        $to = $conversation->history_cursor_at
            ?: $conversation->messages()
                ->whereNotNull('sent_at')
                ->min('sent_at');
        $history = $provider->history(
            $conversation->instance,
            $conversation->remote_jid,
            100,
            null,
            $to ? Carbon::parse($to) : null
        );

        Cache::forget("topweb-chat:history-unavailable:{$conversation->instance_id}");
        Cache::forget("topweb-chat:provider-unavailable:{$conversation->instance_id}");

        $this->persist(
            $conversation,
            array_reverse($history['messages']),
            false
        );

        $oldest = $this->oldestTimestamp($history['messages']);

        if (
            $history['has_more']
            && $oldest
            && (! $to || ! Carbon::parse($to)->equalTo($oldest))
        ) {
            $conversation->update(['history_cursor_at' => $oldest]);
            self::dispatch($conversation->id, true)
                ->delay(now()->addSeconds(2));

            return;
        }

        $conversation->update([
            'history_cursor_at' => null,
            'history_backfilled_at' => now(),
        ]);
    }

    private function continueRecentSync(array $history, ?string $from): void
    {
        if (! $history['has_more']) {
            return;
        }

        $oldest = $this->oldestTimestamp($history['messages']);

        if (
            ! $oldest
            || ($this->to && Carbon::parse($this->to)->equalTo($oldest))
        ) {
            return;
        }

        self::dispatch(
            $this->conversationId,
            false,
            $from,
            $oldest->toIso8601String()
        )->delay(now()->addSeconds(2));
    }

    private function oldestTimestamp(array $messages): ?Carbon
    {
        return collect($messages)
            ->pluck('timestamp')
            ->filter(fn ($timestamp) => $timestamp !== null)
            ->map(fn ($timestamp) => $this->parseTimestamp($timestamp))
            ->sort()
            ->first();
    }

    private function persist(
        Conversation $conversation,
        array $messages,
        bool $countUnread
    ): void {
        $mediaMessageIds = DB::transaction(function () use ($conversation, $messages, $countUnread) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);
            $latestAt = $lockedConversation->last_message_at;
            $newIncoming = 0;
            $mediaMessageIds = [];

            foreach ($messages as $providerMessage) {
                $providerMessageId = $providerMessage['id'] ?? null;

                if (! $providerMessageId) {
                    continue;
                }

                $sentAt = isset($providerMessage['timestamp'])
                    ? $this->parseTimestamp($providerMessage['timestamp'])
                    : now();
                $providerKey = hash(
                    'sha256',
                    $lockedConversation->instance_id.'|'.$providerMessageId
                );
                $message = Message::query()->firstOrCreate([
                    'provider_message_key' => $providerKey,
                ], [
                    'conversation_id' => $lockedConversation->id,
                    'provider_message_id' => $providerMessageId,
                    'direction' => ($providerMessage['fromMe'] ?? false)
                        ? 'outgoing'
                        : 'incoming',
                    'type' => $providerMessage['type'] ?? 'text',
                    'content' => $providerMessage['content'] ?? null,
                    'status' => ($providerMessage['fromMe'] ?? false)
                        ? 'sent'
                        : 'received',
                    'source' => 'openwa_history',
                    'metadata' => [
                        'chat_jid' => $providerMessage['chatJid'] ?? null,
                        'sender_jid' => $providerMessage['senderJid'] ?? null,
                        'has_media' => (bool) ($providerMessage['hasMedia'] ?? false),
                        'media_status' => ($providerMessage['hasMedia'] ?? false)
                            ? 'queued'
                            : null,
                    ],
                    'sent_at' => $sentAt,
                ]);

                if (
                    ($providerMessage['hasMedia'] ?? false)
                    && ! $message->mediaIsStored()
                ) {
                    $mediaMessageIds[] = $message->id;
                }

                if (
                    $countUnread
                    && $message->wasRecentlyCreated
                    && $message->direction === 'incoming'
                ) {
                    $newIncoming++;
                }

                if (! $latestAt || $sentAt->greaterThan($latestAt)) {
                    $latestAt = $sentAt;
                }
            }

            $lockedConversation->update([
                'last_message_at' => $latestAt,
                'unread_count' => $lockedConversation->unread_count + $newIncoming,
            ]);

            return array_values(array_unique($mediaMessageIds));
        }, 3);

        foreach ($mediaMessageIds as $messageId) {
            DownloadMessageMedia::dispatch($messageId);
        }
    }

    private function parseTimestamp(mixed $timestamp): Carbon
    {
        return is_numeric($timestamp)
            ? Carbon::createFromTimestampUTC((int) $timestamp)
                ->setTimezone(config('app.timezone'))
            : Carbon::parse($timestamp);
    }
}
