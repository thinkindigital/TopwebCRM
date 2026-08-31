<?php

namespace Webkul\TopwebChat\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\TopwebChat\Exceptions\ProviderRequestException;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;

class MarkConversationRead implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public array $backoff = [15, 60];

    public function __construct(public int $conversationId) {}

    public function handle(MessagingProvider $provider): void
    {
        $conversation = Conversation::query()
            ->with('instance')
            ->findOrFail($this->conversationId);
        $readStartedAt = now();

        try {
            $provider->markChatRead(
                $conversation->instance,
                $conversation->remote_jid
            );
            Cache::forget("topweb-chat:read-unavailable:{$conversation->instance_id}");
        } catch (ProviderRequestException $exception) {
            if ($exception->statusCode === 429) {
                $this->release($exception->retryAfter);

                return;
            }

            if ($exception->statusCode !== 501) {
                throw $exception;
            }

            Cache::put(
                "topweb-chat:read-unavailable:{$conversation->instance_id}",
                true,
                now()->addMinutes(5)
            );

            Log::warning('OpenWA read synchronization is unavailable.', [
                'conversation_id' => $conversation->id,
                'instance_id' => $conversation->instance_id,
                'status_code' => $exception->statusCode,
            ]);

            return;
        }

        DB::transaction(function () use ($conversation, $readStartedAt) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);
            $unreadCount = $lockedConversation->messages()
                ->where('direction', 'incoming')
                ->where('created_at', '>', $readStartedAt)
                ->count();

            $lockedConversation->update(['unread_count' => $unreadCount]);
        }, 3);
    }
}
