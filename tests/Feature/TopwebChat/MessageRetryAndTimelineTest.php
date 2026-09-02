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
    $processor = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Services/WebhookProcessor.php')
    );

    expect($view)->toContain(
        'min-h-0 flex-1 flex-col justify-start',
        'height: clamp(30rem, calc(100dvh - 10rem), 48rem)',
        'style="min-height: 0; flex: 1 1 auto; overflow-y: auto;"',
        'id="topweb-chat-send-form"',
        'style="flex: 0 0 auto;"',
        'data-retry-url',
        'timeline.scrollHeight - timeline.scrollTop - timeline.clientHeight < 100',
        'lastMessagesSignature',
        'distanceFromBottom',
        'window.requestAnimationFrame(restoreScroll)',
        "replace('_', '-')",
        'new Intl.DateTimeFormat(browserLocale',
        "console.error('TopwebChat refresh failed.'",
        "window.setTimeout(() => {",
        "timeline_connected: timeline.isConnected",
        "client.render_mismatch",
        "topweb-chat-sync-status",
        "cache: 'no-store'",
        'window.setTimeout(poll, 3000)',
        "message.media_mime?.startsWith('image/')",
        "timeline.scrollTo({ top: timeline.scrollHeight, behavior: 'smooth' })",
        "event.key === 'Enter' && !event.shiftKey"
    )->and(strpos($view, 'id="topweb-chat-timeline"'))
        ->toBeLessThan(strpos($view, 'id="topweb-chat-send-form"'))
        ->and($controller)->toContain(
            "orderByRaw('COALESCE(sent_at, created_at) DESC')",
            "->orderByDesc('id')",
            '->reverse()'
        )->and($processor)->toContain(
            "str_ends_with(\$remoteId, '@newsletter')",
            "str_ends_with(\$remoteId, '@broadcast')"
        );
});

it('keeps media behind an authorized private route', function () {
    $routes = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Routes/admin.php')
    );
    $controller = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/ConversationController.php')
    );
    $job = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Jobs/DownloadMessageMedia.php')
    );

    expect($routes)->toContain(
        "name('admin.topweb_chat.messages.media')"
    )->and($controller)->toContain(
        "hasPermission('topweb_chat.inbox.view')",
        '$this->access->authorizeView($user, $conversation)',
        '$this->sensitiveData->authorize($user)',
        '$message->conversation_id === $conversation->id',
        '$message->mediaIsStored()'
    )->and($job)->toContain(
        'SensitiveFileService',
        "'media_status' => 'stored'",
        "config('topweb-chat.openwa.media_max_bytes'"
    );
});

it('uses Topweb Digital branding without the legacy open-source footer copy', function () {
    $layoutConfig = file_get_contents(
        base_path('packages/Webkul/Admin/src/Config/core_config.php')
    );
    $login = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/sessions/login.blade.php')
    );

    expect($layoutConfig)->toContain(
        'Desenvolvido por',
        'https://topwebdigital.com.br',
        'Topweb Digital'
    )->not->toContain('an open-source project by')
        ->and($login)->toContain('https://topwebdigital.com.br')
        ->not->toContain('https://webkul.com/');
});
