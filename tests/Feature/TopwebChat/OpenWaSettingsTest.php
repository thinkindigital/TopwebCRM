<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Webkul\TopwebChat\Http\Controllers\SettingsController;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\TopwebChat\Services\WebhookUrlService;

beforeEach(function () {
    Schema::dropIfExists('topweb_chat_instances');
    Schema::dropIfExists('users');

    Schema::create('topweb_chat_instances', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('provider');
        $table->uuid('session_uuid')->nullable();
        $table->text('token');
        $table->text('webhook_secret');
        $table->string('base_url')->nullable();
        $table->string('status')->nullable();
        $table->boolean('enabled')->default(true);
        $table->boolean('engine_loaded')->default(false);
        $table->json('restriction')->nullable();
        $table->timestamp('last_connected_at')->nullable();
        $table->timestamp('last_synced_at')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->unsignedBigInteger('role_id')->nullable();
        $table->timestamps();
    });

    Auth::shouldReceive('guard->user')->andReturn((object) [
        'role' => (object) ['permission_type' => 'all'],
    ]);
});

it('provides administrators sanitized OpenWA health and sessions', function () {
    $instance = Instance::query()->create([
        'name' => 'OpenWA local',
        'provider' => 'openwa',
        'session_uuid' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
        'token' => 'private-api-key',
        'webhook_secret' => 'private-webhook-secret',
        'base_url' => 'http://openwa.test:2785',
        'enabled' => true,
    ]);

    $provider = mock(MessagingProvider::class, function (MockInterface $mock) use ($instance) {
        $mock->shouldReceive('health')->once()->withArgs(fn (Instance $value) => $value->is($instance))->andReturn([
            'status' => 'ok',
            'version' => '0.23.0',
            'secret' => 'remote-health-secret',
        ]);
        $mock->shouldReceive('listSessions')->once()->withArgs(fn (Instance $value) => $value->is($instance))->andReturn([
            [
                'id' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
                'name' => 'suporte-1',
                'status' => 'disconnected',
                'engineLoaded' => false,
                'config' => ['proxyUrl' => 'http://private-proxy.test'],
            ],
        ]);
    });

    $view = (new SettingsController($provider, mock(WebhookUrlService::class)))->index();
    $data = $view->getData();
    $html = view('topweb_chat::settings.openwa-sessions', [
        'openWaHealth' => $data['openWaHealth'],
        'openWaSessions' => $data['openWaSessions'],
    ])->render();

    expect($data['openWaHealth'])->toBe([
        'status' => 'ok',
        'version' => '0.23.0',
    ])->and($data['openWaSessions'])->toBe([
        [
            'id' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
            'name' => 'suporte-1',
            'status' => 'disconnected',
            'engine_loaded' => false,
        ],
    ])->and(json_encode([
        $data['openWaHealth'],
        $data['openWaSessions'],
    ]))->not->toContain(
        'private-api-key',
        'private-webhook-secret',
        'remote-health-secret',
        'private-proxy.test',
    )->and($html)->toContain('0.23.0', 'suporte-1', 'disconnected')
        ->not->toContain(
            'private-api-key',
            'private-webhook-secret',
            'remote-health-secret',
            'private-proxy.test',
        );
});

it('keeps Settings available when OpenWA cannot be reached', function () {
    $instance = Instance::query()->create([
        'name' => 'OpenWA local',
        'provider' => 'openwa',
        'session_uuid' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
        'token' => 'private-api-key',
        'webhook_secret' => 'private-webhook-secret',
        'base_url' => 'http://openwa.test:2785',
        'enabled' => true,
    ]);

    $provider = mock(MessagingProvider::class, function (MockInterface $mock) use ($instance) {
        $mock->shouldReceive('health')->once()->withArgs(fn (Instance $value) => $value->is($instance))
            ->andThrow(new RuntimeException('connection failed with private-api-key'));
        $mock->shouldNotReceive('listSessions');
    });

    $view = (new SettingsController($provider, mock(WebhookUrlService::class)))->index();

    expect($view->getData()['openWaHealth'])->toBeNull()
        ->and($view->getData()['openWaSessions'])->toBe([])
        ->and($view->getData()['openWaUnavailable'])->toBeTrue();
});
