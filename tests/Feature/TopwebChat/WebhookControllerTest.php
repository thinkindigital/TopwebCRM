<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Middleware\Bouncer;
use Webkul\Admin\Http\Middleware\Locale;
use Webkul\TopwebChat\Http\Controllers\WebhookController;
use Webkul\TopwebChat\Models\Instance;

it('keeps the OpenWA webhook outside administrative middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('api.topweb_chat.webhooks.openwa')
        ->gatherMiddleware();

    expect($middleware)->not->toContain(Bouncer::class, Locale::class);
});

it('accepts a signed OpenWA test event', function () {
    $secret = 'test-webhook-secret';
    $payload = json_encode([
        'event' => 'test',
        'sessionId' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
        'data' => ['message' => 'This is a test webhook from OpenWA'],
    ]);
    $request = Request::create(
        '/api/topweb-chat/webhooks/openwa/1',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_OPENWA_SIGNATURE' => 'sha256='.hash_hmac('sha256', $payload, $secret),
        ],
        content: $payload,
    );
    $instance = new Instance([
        'provider' => 'openwa',
        'webhook_secret' => $secret,
        'enabled' => true,
    ]);

    $response = app(WebhookController::class)->store($request, $instance);

    expect($response->getStatusCode())->toBe(202)
        ->and($response->getData(true))->toBe(['accepted' => true]);
});

it('returns JSON validation errors instead of redirecting invalid webhooks', function () {
    $secret = 'test-webhook-secret';
    $payload = '{}';
    $request = Request::create(
        '/api/topweb-chat/webhooks/openwa/1',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_OPENWA_SIGNATURE' => 'sha256='.hash_hmac('sha256', $payload, $secret),
        ],
        content: $payload,
    );
    $instance = new Instance([
        'provider' => 'openwa',
        'webhook_secret' => $secret,
        'enabled' => true,
    ]);

    $response = app(WebhookController::class)->store($request, $instance);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->headers->get('content-type'))->toContain('application/json')
        ->and($response->getData(true))->toHaveKey('errors.event');
});
