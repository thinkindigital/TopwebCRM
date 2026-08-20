<?php

namespace Webkul\TopwebChat\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;
use Webkul\TopwebChat\Exceptions\ProviderRequestException;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\TopwebChat\Services\RemoteIdentityService;

class SendMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(
        MessagingProvider $provider,
        RemoteIdentityService $remoteIdentity
    ): void {
        $message = Message::query()
            ->with('conversation.instance')
            ->findOrFail($this->messageId);

        if ($message->status !== 'queued') {
            return;
        }

        if (
            ! $message->conversation->instance->enabled
            || $message->conversation->instance->status !== 'connected'
        ) {
            $message->update([
                'status' => 'failed',
                'failed_at' => now(),
                'last_error' => 'provider_instance_not_connected',
            ]);

            return;
        }

        $message->update([
            'status' => 'sending',
            'attempts' => $message->attempts + 1,
            'last_error' => null,
        ]);

        try {
            $result = $provider->sendText(
                $message->conversation->instance,
                $message->conversation->remote_jid,
                (string) $message->content
            );
        } catch (ProviderRequestException $exception) {
            if ($exception->statusCode === 429) {
                if (
                    $message->attempts
                    >= config('topweb-chat.send_max_attempts', 5)
                ) {
                    $message->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'last_error' => 'provider_rate_limit_exhausted',
                    ]);

                    return;
                }

                $message->update([
                    'status' => 'queued',
                    'failed_at' => null,
                    'last_error' => 'provider_rate_limited',
                ]);

                self::dispatch($message->id)
                    ->delay(now()->addSeconds($exception->retryAfter));

                return;
            }

            $message->update([
                'status' => $exception->outcomeUnknown ? 'unknown' : 'failed',
                'failed_at' => now(),
                'last_error' => $exception->outcomeUnknown
                    ? 'provider_request_outcome_unknown'
                    : 'provider_request_rejected',
            ]);

            return;
        } catch (Throwable) {
            $message->update([
                'status' => 'unknown',
                'failed_at' => now(),
                'last_error' => 'provider_request_outcome_unknown',
            ]);

            return;
        }

        $providerMessageId = $result['data']['messageId'] ?? null;
        $sentAt = isset($result['data']['timestamp'])
            ? Carbon::parse($result['data']['timestamp'])
            : now();

        DB::transaction(function () use (
            $message,
            $providerMessageId,
            $result,
            $sentAt,
            $remoteIdentity
        ) {
            $conversation = Message::query()
                ->findOrFail($message->id)
                ->conversation()
                ->lockForUpdate()
                ->firstOrFail();

            $message->update([
                'provider_message_id' => $providerMessageId,
                'provider_message_key' => $providerMessageId
                    ? hash(
                        'sha256',
                        $conversation->instance_id.'|'.$providerMessageId
                    )
                    : null,
                'status' => $result['status'] ?? 'sent',
                'sent_at' => $sentAt,
                'failed_at' => null,
                'metadata' => [
                    'chat_type' => data_get($result, 'data.chat.isGroup')
                        ? 'group'
                        : 'private',
                ],
            ]);

            $conversationUpdates = [
                'last_message_at' => ! $conversation->last_message_at
                    || $sentAt->greaterThan($conversation->last_message_at)
                        ? $sentAt
                        : $conversation->last_message_at,
            ];

            if ($remoteJid = data_get($result, 'data.chat.jid')) {
                $conversationUpdates['remote_jid'] = $remoteJid;
                $conversationUpdates['remote_jid_key'] = $remoteIdentity->key($remoteJid);
            }

            $conversation->update($conversationUpdates);
        }, 3);
    }
}
