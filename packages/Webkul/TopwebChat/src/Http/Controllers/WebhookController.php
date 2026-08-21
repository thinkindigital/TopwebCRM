<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Webkul\TopwebChat\Jobs\ProcessWebhookEvent;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\WebhookEvent;

class WebhookController
{
    public function store(Request $request, Instance $instance): JsonResponse
    {
        abort_unless($instance->enabled && $instance->isOpenWA(), 404);

        // Validate HMAC signature
        $secret = $instance->webhook_secret;
        $signature = $request->header('X-OpenWA-Signature');
        $rawBody = $request->getContent();

        if (! $this->validateHmac($rawBody, $signature, $secret)) {
            Log::warning('Invalid OpenWA webhook signature', [
                'instance_id' => $instance->id,
                'provided' => $signature,
            ]);
            abort(401, 'Invalid signature');
        }

        $data = $request->validate([
            'event' => ['required', 'string', 'max:120'],
            'timestamp' => ['nullable', 'string'],
            'sessionId' => ['nullable', 'string'],
            'idempotencyKey' => ['nullable', 'string'],
            'deliveryId' => ['nullable', 'string'],
            'data' => ['required', 'array'],
        ]);

        $eventKey = $this->eventKey($instance, $data);
        $event = WebhookEvent::query()->firstOrCreate([
            'event_key' => $eventKey,
        ], [
            'instance_id' => $instance->id,
            'event_type' => $data['event'],
            'payload' => $data,
            'status' => 'pending',
        ]);

        if ($event->wasRecentlyCreated) {
            ProcessWebhookEvent::dispatchAfterResponse($event->id);
        }

        return response()->json(['accepted' => true], 202);
    }

    private function validateHmac(string $payload, string $signature, string $secret): bool
    {
        if (! $signature) {
            return false;
        }

        // Signature format: "sha256=<hex>"
        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    private function eventKey(Instance $instance, array $payload): string
    {
        $event = $payload['event'];
        $data = $payload['data'] ?? [];
        $sessionId = $payload['sessionId'] ?? '';

        $identity = match ($event) {
            'message.received', 'message.sent' => data_get($data, 'data.id') ?? data_get($data, 'id'),
            'message.ack', 'message.failed' => data_get($data, 'data.id').'|'.data_get($data, 'data.status'),
            'message.revoked' => data_get($data, 'data.revokedId') ?? data_get($data, 'data.id'),
            'message.reaction' => implode(',', [
                data_get($data, 'data.messageId'),
                data_get($data, 'data.senderId'),
                data_get($data, 'data.reaction'),
            ]),
            'message.edited' => data_get($data, 'data.messageId').'|'.data_get($data, 'data.timestamp'),
            'session.status' => $sessionId.'|'.data_get($data, 'data.status'),
            'session.authenticated' => $sessionId.'|'.hash('sha256', json_encode($data)).'|'.data_get($data, 'data.timestamp'),
            'session.disconnected' => $sessionId.'|'.hash('sha256', data_get($data, 'data.reason')).'|'.data_get($data, 'data.timestamp'),
            'group.join', 'group.leave' => data_get($data, 'data.groupId').'|'.hash('sha256', json_encode(data_get($data, 'data.participantIds', []))).'|join|leave|'.data_get($data, 'data.timestamp'),
            'group.update' => data_get($data, 'data.groupId').'|'.hash('sha256', json_encode(data_get($data, 'data.changes', []))).'|'.data_get($data, 'data.timestamp'),
            'group.join_request' => data_get($data, 'data.groupId').'|'.hash('sha256', json_encode(data_get($data, 'data.participantIds', []))).'|join_request|'.data_get($data, 'data.timestamp'),
            'call.received' => data_get($data, 'data.callId'),
            'call.accepted', 'call.rejected', 'call.missed' => data_get($data, 'data.callId').'|'.data_get($data, 'data.outcome').'|'.data_get($data, 'data.timestamp'),
            'status.received' => data_get($data, 'data.statusId').'|'.data_get($data, 'data.timestamp'),
            default => json_encode($data),
        };

        $idempotencyKey = $payload['idempotencyKey'] ?? $identity;

        return hash('sha256', $instance->id.'|'.$event.'|'.$idempotencyKey);
    }
}
