<?php

use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Services\WebhookUrlService;

it('builds an OpenWA webhook URL for local development', function () {
    config([
        'app.url' => 'http://localhost:8000',
        'topweb-chat.public_url' => null,
    ]);

    $instance = new Instance;
    $instance->id = 7;

    expect(app(WebhookUrlService::class)->forInstance($instance))
        ->toBe('http://localhost:8000/api/topweb-chat/webhooks/openwa/7');
});
