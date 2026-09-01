<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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

beforeEach(function () {
    Cache::flush();
});

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

it('accepts an OpenWA read no-op when no message is available in engine memory', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/chats/read' => Http::response([
            'success' => false,
        ]),
    ]);

    app(OpenWaProvider::class)->markChatRead(
        openWaInstance(),
        '5511999999999@c.us'
    );

    Http::assertSentCount(1);
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

it('resolves a phone number to an OpenWA chat id before sending', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/contacts/check/*' => Http::response([
            'number' => '5511999999999',
            'exists' => true,
            'whatsappId' => '5511999999999@c.us',
        ]),
        'http://openwa.test:2785/api/sessions/*/messages/send-text' => Http::response([
            'messageId' => 'true_5511999999999@c.us_3EB0ABCD',
            'timestamp' => 1719312000,
        ], 201),
    ]);

    app(OpenWaProvider::class)->sendText(
        openWaInstance(),
        '5511999999999',
        'Ola'
    );

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->data()['chatId'] === '5511999999999@c.us');
});

it('resolves an OpenWA privacy id through the official contact endpoint', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/contacts/*/phone' => Http::response([
            'phone' => '5511999999999',
        ]),
    ]);

    $phone = app(OpenWaProvider::class)->getContactPhone(
        openWaInstance(),
        '12345678901234@lid'
    );

    expect($phone)->toBe('5511999999999');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'http://openwa.test:2785/api/sessions/be23262e-5ffb-405b-95e3-8658f043fb30/contacts/12345678901234@lid/phone');
});

it('downloads media bytes from the OpenWA private media endpoint', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/messages/*/*/media' => Http::response(
            'binary-image-contents',
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $contents = app(OpenWaProvider::class)->downloadMedia(
        openWaInstance(),
        '12345678901234@lid',
        'message-id'
    );

    expect($contents)->toBe('binary-image-contents');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'http://openwa.test:2785/api/sessions/be23262e-5ffb-405b-95e3-8658f043fb30/messages/12345678901234@lid/message-id/media');
});

it('loads only persisted OpenWA history for one conversation', function () {
    Http::fake([
        'http://openwa.test:2785/api/sessions/*/contacts/check/*' => Http::response([
            'number' => '5511999999999',
            'exists' => true,
            'whatsappId' => '5511999999999@c.us',
        ]),
        'http://openwa.test:2785/api/sessions/*/messages*' => Http::response([
            'messages' => [[
                'id' => 'local-id',
                'waMessageId' => 'wa-id',
                'chatId' => '5511999999999@c.us',
                'from' => '5511999999999@c.us',
                'body' => 'Historico',
                'type' => 'text',
                'direction' => 'incoming',
                'timestamp' => 1719312000,
            ]],
            'total' => 1,
        ]),
    ]);

    $history = app(OpenWaProvider::class)->history(
        openWaInstance(),
        '5511999999999'
    );

    expect($history['has_more'])->toBeFalse()
        ->and($history['chat_jid'])->toBe('5511999999999@c.us')
        ->and($history['messages'])->toHaveCount(1)
        ->and($history['messages'][0])->toMatchArray([
            'id' => 'wa-id',
            'content' => 'Historico',
            'fromMe' => false,
        ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_contains($request->url(), '/messages?')
        && str_contains($request->url(), 'chatId=5511999999999%40c.us'));
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
