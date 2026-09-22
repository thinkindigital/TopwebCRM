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
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/workspace.blade.php')
    );
    $controller = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/ConversationController.php')
    );
    $processor = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Services/WebhookProcessor.php')
    );

    $partial = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/timeline-messages.blade.php')
    );
    $composer = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/composer.blade.php')
    );
    $header = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/conversation-header.blade.php')
    );
    $runtime = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/chat-runtime.blade.php')
    );

    expect($view)->toContain(
        'min-h-0 flex-1 flex-col justify-start',
        'conversations.partials.composer',
        'conversations.partials.timeline-messages'
    );
    expect($runtime)->toContain(
        'timeline.scrollHeight - timeline.scrollTop - timeline.clientHeight < 100',
        'renderFragment',
        'distanceFromBottom',
        'window.requestAnimationFrame(restoreScroll)',
        "replaceAll('_', '-')",
        'getCanonicalLocales',
        'toLocaleTimeString(browserLocale)',
        "console.error('TopwebChat refresh failed.'",
        'window.setTimeout(() => {',
        'timeline_connected: timeline.isConnected',
        "cache: 'no-store'",
        'window.setTimeout(poll, 3000)',
        "timeline.scrollTo({ top: timeline.scrollHeight, behavior: 'smooth' })",
        "event.key === 'Enter' && !event.shiftKey"
    );
    expect($header)->toContain('topweb-chat-sync-status');
    expect($partial)->toContain(
        'topweb-chat-poll-meta',
        'topweb-chat-date-separator',
        'data-message-id',
        'data-retry-url'
    );
    expect($composer)->toContain(
        'id="topweb-chat-send-form"',
        'flex-shrink-0',
        'name="operation_key"'
    );
    expect(strpos($view, 'timeline-messages'))
        ->toBeLessThan(strpos($view, 'partials.composer'));
    expect($controller)->toContain(
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

it('fails oversized attachments locally with FIL-6001 and flags dead connections', function () {
    // #111: pré-checagem no browser (sem upload inútil) + NET-3001 sem resposta.
    $runtime = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/chat-runtime.blade.php')
    );
    $composer = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/composer.blade.php')
    );

    expect($composer)->toContain('data-max-file-kb');
    expect($runtime)->toContain(
        'maxFileKb',
        'file.size',
        'tooLarge',
        'commFailed',
        'messages.comm_failed',
        'error instanceof TypeError'
    );
    expect(trans('topweb_chat::app.messages.comm_failed'))->toContain('NET-3001');
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
