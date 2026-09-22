<?php

namespace Webkul\TopwebChat\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Webkul\TopwebChat\Exceptions\ProviderRequestException;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\TopwebChat\Services\AttendanceService;
use Webkul\TopwebChat\Services\RemoteIdentityService;
use Webkul\TopwebChat\Support\TopwebChatError;

class SendMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(
        MessagingProvider $provider,
        RemoteIdentityService $remoteIdentity,
        AttendanceService $attendances
    ): void {
        $message = Message::query()
            ->with('conversation.instance')
            ->findOrFail($this->messageId);

        if ($message->status !== 'queued') {
            return;
        }

        if (
            ! $message->conversation->instance->enabled
            || $message->conversation->instance->status !== 'ready'
        ) {
            $this->recordFailure(
                $message,
                'provider_instance_not_connected',
                TopwebChatError::API_PROVIDER_BUSY,
                'failed'
            );

            return;
        }

        $message->update([
            'status' => 'sending',
            'attempts' => $message->attempts + 1,
            'last_error' => null,
            'error_code' => null,
            'trace_id' => null,
        ]);

        try {
            if ($message->hasMedia()) {
                $mediaPayload = $this->mediaPayload($message);

                if ($mediaPayload === null) {
                    // mediaPayload already updated the message with the error
                    return;
                }

                $result = $provider->sendMedia(
                    $message->conversation->instance,
                    $message->conversation->remote_jid,
                    $mediaPayload
                );
            } else {
                $result = $provider->sendText(
                    $message->conversation->instance,
                    $message->conversation->remote_jid,
                    (string) $message->content
                );
            }
        } catch (ProviderRequestException $exception) {
            $errorCode = TopwebChatError::forProvider(
                $exception->statusCode,
                $exception->outcomeUnknown
            );

            if ($exception->statusCode === 429) {
                if (
                    $message->attempts
                    >= config('topweb-chat.send_max_attempts', 5)
                ) {
                    $this->recordFailure(
                        $message,
                        'provider_rate_limit_exhausted',
                        $errorCode,
                        'failed',
                        $exception->statusCode
                    );

                    return;
                }

                $this->recordFailure(
                    $message,
                    'provider_rate_limited',
                    $errorCode,
                    'queued',
                    $exception->statusCode
                );

                self::dispatch($message->id)
                    ->delay(now()->addSeconds($exception->retryAfter));

                return;
            }

            $this->recordFailure(
                $message,
                $exception->outcomeUnknown
                    ? 'provider_request_outcome_unknown'
                    : 'provider_request_rejected',
                $errorCode,
                $exception->outcomeUnknown ? 'unknown' : 'failed',
                $exception->statusCode,
                $exception->outcomeUnknown
            );

            return;
        } catch (Throwable) {
            $this->recordFailure(
                $message,
                'provider_request_outcome_unknown',
                TopwebChatError::API_UNCLASSIFIED_FAILURE,
                'unknown',
                null,
                true
            );

            return;
        }

        $providerMessageId = $result['messageId'] ?? null;
        $sentAt = isset($result['timestamp'])
            ? Carbon::createFromTimestampUTC((int) $result['timestamp'])
                ->setTimezone(config('app.timezone'))
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
                'error_code' => null,
                'trace_id' => null,
                'metadata' => array_merge($message->metadata ?? [], [
                    'chat_type' => data_get($result, 'data.chat.isGroup')
                        ? 'group'
                        : 'private',
                ]),
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

        $attendances->recordRealMessage($message->fresh());
    }

    /**
     * @return array{base64: string, mimetype: string, filename: string, caption: ?string}|null
     */
    private function mediaPayload(Message $message): ?array
    {
        $path = data_get($message->metadata, 'media_path');
        $disk = Storage::disk(config('sensitive-data.storage.disk', 'private'));

        if (! $path || ! $disk->exists($path)) {
            $this->recordFailure(
                $message,
                'media_file_missing',
                TopwebChatError::STO_OBJECT_NOT_FOUND,
                'failed'
            );

            return null;
        }

        return [
            'base64' => base64_encode($disk->get($path)),
            'mimetype' => data_get($message->metadata, 'media_mime', 'application/octet-stream'),
            'filename' => data_get($message->metadata, 'media_original_name', "arquivo-{$message->id}"),
            'caption' => $message->content,
        ];
    }

    private function recordFailure(
        Message $message,
        string $legacyError,
        string $errorCode,
        string $status,
        ?int $httpStatus = null,
        bool $outcomeUnknown = false
    ): void {
        $traceId = TopwebChatError::traceId();
        $definition = TopwebChatError::definition($errorCode);

        $message->update([
            'status' => $status,
            'failed_at' => $status === 'queued' ? null : now(),
            'last_error' => $legacyError,
            'error_code' => $errorCode,
            'trace_id' => $traceId,
        ]);

        Log::log($definition['severity'], 'TopwebChat message send failed.', [
            'error_code' => $errorCode,
            'trace_id' => $traceId,
            'technical_event' => $definition['event'],
            'severity' => $definition['severity'],
            'retryable' => $definition['retryable'],
            'http_status' => $httpStatus ?? $definition['http_status'],
            'operation' => 'message.send',
            'message_id' => $message->id,
            'instance_id' => $message->conversation->instance_id,
            'provider' => $message->conversation->instance->provider,
            'attempt' => $message->attempts,
            'outcome_unknown' => $outcomeUnknown,
        ]);
    }
}
