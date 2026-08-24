<?php

use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Services\MessageService;

it('allows retry only when the provider was never called', function () {
    $retryable = new Message([
        'direction' => 'outgoing',
        'status' => 'failed',
        'last_error' => 'provider_instance_not_connected',
    ]);
    $unknown = new Message([
        'direction' => 'outgoing',
        'status' => 'unknown',
        'last_error' => 'provider_request_outcome_unknown',
    ]);
    $rejected = new Message([
        'direction' => 'outgoing',
        'status' => 'failed',
        'last_error' => 'provider_request_rejected',
    ]);
    $incoming = new Message([
        'direction' => 'incoming',
        'status' => 'failed',
        'last_error' => 'provider_instance_not_connected',
    ]);
    $alreadyAccepted = new Message([
        'direction' => 'outgoing',
        'status' => 'failed',
        'last_error' => 'provider_instance_not_connected',
        'provider_message_id' => 'remote-message-id',
    ]);
    $queued = new Message([
        'direction' => 'outgoing',
        'status' => 'queued',
        'last_error' => 'provider_instance_not_connected',
    ]);

    $service = app(MessageService::class);

    expect($service->canRetry($retryable))->toBeTrue()
        ->and($service->canRetry($unknown))->toBeFalse()
        ->and($service->canRetry($rejected))->toBeFalse()
        ->and($service->canRetry($incoming))->toBeFalse()
        ->and($service->canRetry($alreadyAccepted))->toBeFalse()
        ->and($service->canRetry($queued))->toBeFalse();
});

it('keeps the composer outside the scrollable chronological timeline', function () {
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );
    $controller = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/ConversationController.php')
    );

    expect($view)->toContain(
        'min-h-0 flex-1 content-start',
        'height: clamp(30rem, calc(100dvh - 10rem), 48rem)',
        'style="min-height: 0; flex: 1 1 auto; overflow-y: auto;"',
        'id="topweb-chat-send-form"',
        'style="flex: 0 0 auto;"',
        'data-retry-url',
        'timeline.scrollHeight - timeline.scrollTop - timeline.clientHeight < 80',
        'nextAnchor.offsetTop - anchorOffset',
        "cache: 'no-store'",
        'window.setTimeout(poll, 3000)'
    )->and(strpos($view, 'id="topweb-chat-timeline"'))
        ->toBeLessThan(strpos($view, 'id="topweb-chat-send-form"'))
        ->and($controller)->toContain(
            "orderByRaw('COALESCE(sent_at, created_at) DESC')",
            "->orderByDesc('id')",
            '->reverse()'
        );
});
