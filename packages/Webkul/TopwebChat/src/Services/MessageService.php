<?php

namespace Webkul\TopwebChat\Services;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Webkul\TopwebChat\Jobs\SendMessage;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;
use Webkul\User\Models\User;

class MessageService
{
    public function __construct(protected ConversationAccessService $access) {}

    public function queueText(
        Conversation $conversation,
        User $user,
        string $content,
        string $operationKey
    ): Message {
        $message = DB::transaction(function () use (
            $conversation,
            $user,
            $content,
            $operationKey
        ) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ($lockedConversation->assigned_user_id === null) {
                $lockedConversation->update(['assigned_user_id' => $user->id]);
            } elseif (
                $lockedConversation->assigned_user_id !== $user->id
                && ! $this->access->isAdministrator($user)
            ) {
                throw new AuthorizationException;
            }

            $existingMessage = Message::query()
                ->where('conversation_id', $lockedConversation->id)
                ->where('operation_key', $operationKey)
                ->first();

            if ($existingMessage) {
                return $existingMessage;
            }

            $instance = $lockedConversation->instance()->first();

            if (! $instance?->enabled || $instance->status !== 'ready') {
                throw new DomainException(
                    trans('topweb_chat::app.messages.instance_not_connected')
                );
            }

            return Message::query()->create([
                'conversation_id' => $lockedConversation->id,
                'user_id' => $user->id,
                'operation_key' => $operationKey,
                'direction' => 'outgoing',
                'type' => 'text',
                'content' => $content,
                'status' => 'queued',
                'source' => 'topweb_chat',
            ]);
        });

        if ($message->wasRecentlyCreated) {
            SendMessage::dispatchAfterResponse($message->id);
        }

        return $message->fresh();
    }
}
