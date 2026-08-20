<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\TopwebChat\Jobs\ProcessWebhookEvent;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\WebhookEvent;

class WebhookController
{
    public function store(Request $request, Instance $instance): JsonResponse
    {
        abort_unless($instance->enabled && $instance->provider === 'ryzeapi', 404);

        $expectedAuthorization = 'Bearer '.$instance->webhook_secret;
        $providedAuthorization = (string) $request->header('Authorization');

        abort_unless(
            hash_equals($expectedAuthorization, $providedAuthorization),
            401
        );

        $data = $request->validate([
            'event' => ['required', 'string', 'max:120'],
            'data' => ['required', 'array'],
            'instanceData' => ['nullable', 'array'],
        ]);

        unset($data['instanceData']['token']);

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

    private function eventKey(Instance $instance, array $payload): string
    {
        $identity = data_get($payload, 'data.message.id')
            ?: data_get($payload, 'data.id');

        if (! $identity && $payload['event'] === 'message.status') {
            $identity = implode(',', data_get($payload, 'data.messageIds', []))
                .'|'.data_get($payload, 'data.status')
                .'|'.data_get($payload, 'data.timestamp');
        }

        $identity ??= json_encode($payload['data']);

        return hash(
            'sha256',
            $instance->id.'|'.$payload['event'].'|'.$identity
        );
    }
}
