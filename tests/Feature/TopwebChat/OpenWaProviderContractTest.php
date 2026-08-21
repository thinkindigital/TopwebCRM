<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\TopwebChat\Providers\OpenWaProvider;

function openWaInstance(array $attributes = []): Instance
{
    return new Instance(array_merge([
        'name' => 'support-1',
        'provider' => 'openwa',
        'session_uuid' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
        'base_url' => 'http://openwa.test:2785',
        'token' => 'owa_test_key',
        'webhook_secret' => 'test-webhook-secret',
        'enabled' => true,
    ], $attributes));
}

it('lists OpenWA sessions using the authenticated raw API contract', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions*' => Http::response([
            [
                'id' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
                'name' => 'support-1',
                'status' => 'ready',
                'engineLoaded' => true,
            ],
        ]),
    ]);

    $provider = app(OpenWaProvider::class);

    expect($provider)->toBeInstanceOf(MessagingProvider::class)
        ->and($provider->listSessions(openWaInstance()))->toBe([
            [
                'id' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
                'name' => 'support-1',
                'status' => 'ready',
                'engineLoaded' => true,
            ],
        ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'http://openwa.test:2785/api/sessions?limit=1000'
        && $request->hasHeader('X-API-Key', 'owa_test_key'));
});

it('checks the authenticated OpenWA health endpoint', function () {
    Http::fake([
        'http://openwa.test:2785/api/health' => Http::response([
            'status' => 'ok',
            'version' => '0.23.0',
        ]),
    ]);

    $health = app(OpenWaProvider::class)->health(openWaInstance());

    expect($health)->toBe([
        'status' => 'ok',
        'version' => '0.23.0',
    ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'http://openwa.test:2785/api/health'
        && $request->hasHeader('X-API-Key', 'owa_test_key'));
});

it('marks an OpenWA chat as read using the collection endpoint', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/chats/read' => Http::response([
            'success' => true,
        ]),
    ]);

    app(OpenWaProvider::class)->markChatRead(
        openWaInstance(),
        '5511999999999@c.us'
    );

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://openwa.test:2785/api/sessions/be23262e-5ffb-405b-95e3-8658f043fb30/chats/read'
        && $request->data() === ['chatId' => '5511999999999@c.us']);
});

it('creates an OpenWA session without null optional fields', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions' => Http::response([
            'id' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
            'name' => 'support-1',
            'status' => 'created',
            'engineLoaded' => false,
        ], 201),
    ]);

    $session = app(OpenWaProvider::class)->createSession(openWaInstance());

    expect($session['status'])->toBe('created');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://openwa.test:2785/api/sessions'
        && $request->data() === ['name' => 'support-1']);
});

it('returns the OpenWA QR code data URL unchanged', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/qr' => Http::response([
            'qrCode' => 'data:image/png;base64,iVBORw0KGgo=',
            'status' => 'qr_ready',
        ]),
    ]);

    $qr = app(OpenWaProvider::class)->getQrCode(openWaInstance());

    expect($qr)->toBe([
        'qrCode' => 'data:image/png;base64,iVBORw0KGgo=',
        'status' => 'qr_ready',
    ]);
});

it('sends text using the OpenWA raw response contract', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/messages/send-text' => Http::response([
            'messageId' => 'true_5511999999999@c.us_3EB0ABCD',
            'timestamp' => 1719312000,
        ], 201),
    ]);

    $result = app(OpenWaProvider::class)->sendText(
        openWaInstance(),
        '5511999999999@c.us',
        'Ola',
        ['quotedMessageId' => 'quoted-message-id']
    );

    expect($result)->toBe([
        'messageId' => 'true_5511999999999@c.us_3EB0ABCD',
        'timestamp' => 1719312000,
    ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->data() === [
            'chatId' => '5511999999999@c.us',
            'text' => 'Ola',
            'quotedMessageId' => 'quoted-message-id',
        ]);
});

it('registers the literal HMAC secret on an OpenWA webhook', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/webhooks' => Http::response([
            'id' => 'webhook-id',
            'active' => true,
        ], 201),
    ]);

    app(OpenWaProvider::class)->configureWebhook(
        openWaInstance(),
        'https://crm.test/api/topweb-chat/webhooks/openwa/session-id',
        'literal-hmac-secret',
        ['message.received']
    );

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->data()['secret'] === 'literal-hmac-secret'
        && $request->data()['events'] === ['message.received']
        && ! array_key_exists('headers', $request->data())
        && ! array_key_exists('filters', $request->data()));
});

it('lists and tests OpenWA webhooks using raw responses', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/webhooks/webhook-id/test' => Http::response([
            'success' => true,
            'statusCode' => 200,
        ]),
        'http://openwa.test:2785/api/sessions/*/webhooks' => Http::response([
            [
                'id' => 'webhook-id',
                'events' => ['message.received'],
                'active' => true,
            ],
        ]),
    ]);

    $provider = app(OpenWaProvider::class);

    expect($provider->listWebhooks(openWaInstance()))->toHaveCount(1)
        ->and($provider->testWebhook(openWaInstance(), 'webhook-id'))->toBe([
            'success' => true,
            'statusCode' => 200,
        ]);
});
