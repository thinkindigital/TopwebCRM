<?php

namespace Webkul\TopwebChat\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Models\WebhookEvent;

class WebhookProcessor
{
    public function __construct(protected ConversationService $conversationService) {}

    public function process(WebhookEvent $event): void
    {
        match ($event->event_type) {
            'message.received', 'message.sent' => $this->processMessage($event),
            'message.ack', 'message.failed' => $this->processStatus($event),
            'session.status' => $this->processInstanceState($event),
            default => null,
        };

        $event->update([
            'status' => 'processed',
            'processed_at' => now(),
            'failed_at' => null,
            'last_error' => null,
        ]);
    }

    private function processMessage(WebhookEvent $event): void
    {
        $payload = $event->payload;
        $messageData = data_get($payload, 'data', []);
        $providerMessageId = $messageData['id'] ?? null;

        if (! $providerMessageId) {
            return;
        }

        $providerKey = hash('sha256', $event->instance_id.'|'.$providerMessageId);
        $direction = $event->event_type === 'message.sent' || ($messageData['fromMe'] ?? false)
            ? 'outgoing'
            : 'incoming';
        $remoteId = $direction === 'outgoing'
            ? ($messageData['to'] ?? null)
            : ($messageData['senderPhone'] ?? $messageData['from'] ?? null);

        if (! $remoteId) {
            return;
        }

        $conversation = $this->conversationService->findOrCreateInbound(
            $event->instance,
            $remoteId,
            data_get($messageData, 'contact.name') ?: data_get($messageData, 'contact.pushName')
        );

        $timestamp = isset($messageData['timestamp'])
            ? Carbon::createFromTimestamp($messageData['timestamp'])
            : now();
        $type = $messageData['type'] ?? 'text';
        $content = $messageData['body'] ?? null;

        DB::transaction(function () use (
            $conversation,
            $providerMessageId,
            $providerKey,
            $direction,
            $type,
            $content,
            $messageData,
            $timestamp
        ) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);
            $existingMessage = Message::query()
                ->where('provider_message_key', $providerKey)
                ->first();

            if ($existingMessage) {
                if ($existingMessage->status === 'pending') {
                    $existingMessage->update(['status' => 'sent']);
                }

                return;
            }

            Message::query()->create([
                'conversation_id' => $lockedConversation->id,
                'provider_message_id' => $providerMessageId,
                'provider_message_key' => $providerKey,
                'direction' => $direction,
                'type' => $type,
                'content' => $content,
                'status' => $direction === 'incoming' ? 'received' : 'sent',
                'source' => 'openwa',
                'metadata' => [
                    'chat_id' => $messageData['chatId'] ?? null,
                    'is_group' => $messageData['isGroup'] ?? false,
                    'has_media' => $messageData['hasMedia'] ?? false,
                    'kind' => $messageData['kind'] ?? null,
                ],
                'sent_at' => $timestamp,
            ]);

            $lastMessageAt = $lockedConversation->last_message_at;

            $lockedConversation->update([
                'last_message_at' => ! $lastMessageAt
                    || $timestamp->greaterThan($lastMessageAt)
                        ? $timestamp
                        : $lastMessageAt,
                'unread_count' => $direction === 'incoming'
                    ? $lockedConversation->unread_count + 1
                    : $lockedConversation->unread_count,
                'status' => 'open',
            ]);
        }, 3);
    }

    private function processStatus(WebhookEvent $event): void
    {
        $status = data_get($event->payload, 'data.status');
        $timestamp = $event->payload['timestamp'] ?? null;
        $providerMessageId = data_get($event->payload, 'data.messageId')
            ?: data_get($event->payload, 'data.id');

        foreach (array_filter([$providerMessageId]) as $providerMessageId) {
            $message = Message::query()
                ->where(
                    'provider_message_key',
                    hash('sha256', $event->instance_id.'|'.$providerMessageId)
                )
                ->first();

            if (! $message) {
                continue;
            }

            $updates = [
                'status' => $this->newerStatus($message->status, $status),
            ];
            $statusAt = $timestamp ? Carbon::parse($timestamp) : now();

            if ($status === 'delivered') {
                $updates['delivered_at'] = $statusAt;
            } elseif (in_array($status, ['read', 'read_self', 'played', 'played_self'], true)) {
                $updates['read_at'] = $statusAt;
            } elseif ($status === 'failed' && ! $this->isFinalDeliveryState($message->status)) {
                $updates['status'] = 'failed';
                $updates['failed_at'] = $statusAt;
                $updates['last_error'] = 'provider_status_'.$status;
            }

            $message->update($updates);
        }
    }

    private function newerStatus(string $current, ?string $incoming): string
    {
        if (! $incoming) {
            return $current;
        }

        $rank = [
            'queued' => 0,
            'pending' => 0,
            'sending' => 1,
            'sender' => 2,
            'sent' => 2,
            'delivered' => 3,
            'read' => 4,
            'read_self' => 4,
            'played' => 5,
            'played_self' => 5,
        ];

        if (! isset($rank[$incoming])) {
            return $current;
        }

        if (! isset($rank[$current])) {
            return $incoming;
        }

        return $rank[$incoming] >= $rank[$current] ? $incoming : $current;
    }

    private function isFinalDeliveryState(string $status): bool
    {
        return in_array($status, [
            'delivered',
            'read',
            'read_self',
            'played',
            'played_self',
            'received',
        ], true);
    }

    private function processInstanceState(WebhookEvent $event): void
    {
        $state = data_get($event->payload, 'data.status', 'unknown');

        $event->instance->update([
            'status' => $state,
            'last_connected_at' => $state === 'ready'
                ? now()
                : $event->instance->last_connected_at,
            'last_synced_at' => now(),
        ]);
    }
}
