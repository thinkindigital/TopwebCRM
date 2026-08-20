<?php

namespace Webkul\TopwebChat\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

            throw $exception;
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
            ->filter()
            ->map(fn (string $timestamp) => Carbon::parse($timestamp))
            ->sort()
            ->first();
    }

    private function persist(
        Conversation $conversation,
        array $messages,
        bool $countUnread
    ): void {
        DB::transaction(function () use ($conversation, $messages, $countUnread) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);
            $latestAt = $lockedConversation->last_message_at;
            $newIncoming = 0;

            foreach ($messages as $providerMessage) {
                $providerMessageId = $providerMessage['id'] ?? null;

                if (! $providerMessageId) {
                    continue;
                }

                $sentAt = isset($providerMessage['timestamp'])
                    ? Carbon::parse($providerMessage['timestamp'])
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
                    'source' => 'ryzeapi_history',
                    'metadata' => [
                        'chat_jid' => $providerMessage['chatJid'] ?? null,
                        'sender_jid' => $providerMessage['senderJid'] ?? null,
                    ],
                    'sent_at' => $sentAt,
                ]);

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
        }, 3);
    }
}
