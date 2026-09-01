<?php

use App\Services\SensitiveFileService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Webkul\TopwebChat\Jobs\DownloadMessageMedia;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\TopwebChat\Services\ContactResolverService;
use Webkul\TopwebChat\Services\ConversationAccessService;
use Webkul\TopwebChat\Services\ConversationService;
use Webkul\TopwebChat\Services\RemoteIdentityService;

beforeEach(function () {
    Schema::dropIfExists('topweb_chat_messages');
    Schema::dropIfExists('topweb_chat_conversations');
    Schema::dropIfExists('topweb_chat_instances');

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
        $table->timestamps();
    });

    Schema::create('topweb_chat_conversations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('instance_id');
        $table->unsignedBigInteger('person_id');
        $table->text('remote_jid');
        $table->char('remote_jid_key', 64);
        $table->string('status')->default('open');
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('topweb_chat_messages', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->uuid('operation_key')->nullable();
        $table->text('provider_message_id')->nullable();
        $table->char('provider_message_key', 64)->nullable();
        $table->string('direction');
        $table->string('type');
        $table->longText('content')->nullable();
        $table->string('status');
        $table->unsignedInteger('attempts')->default(0);
        $table->string('source');
        $table->longText('metadata')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('read_at')->nullable();
        $table->timestamp('failed_at')->nullable();
        $table->string('last_error')->nullable();
        $table->timestamps();
    });
});

function inboundTestInstance(): Instance
{
    return Instance::query()->create([
        'name' => 'OpenWA test',
        'provider' => 'openwa',
        'session_uuid' => 'be23262e-5ffb-405b-95e3-8658f043fb30',
        'token' => 'private-api-key',
        'webhook_secret' => 'private-webhook-secret',
        'base_url' => 'http://openwa.test:2785',
        'status' => 'ready',
        'enabled' => true,
    ]);
}

it('resolves an inbound lid before reusing the existing phone conversation', function () {
    $instance = inboundTestInstance();
    $identity = app(RemoteIdentityService::class);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'person_id' => 10,
        'remote_jid' => '5511999999999',
        'remote_jid_key' => $identity->key('5511999999999'),
        'status' => 'open',
    ]);
    $provider = mock(MessagingProvider::class);
    $provider->shouldReceive('getContactPhone')
        ->once()
        ->withArgs(fn (Instance $value, string $lid) => $value->is($instance)
            && $lid === '12345678901234@lid')
        ->andReturn('5511999999999');
    $contactResolver = mock(ContactResolverService::class);
    $contactResolver->shouldNotReceive('resolve');

    $resolved = (new ConversationService(
        $identity,
        $contactResolver,
        mock(ConversationAccessService::class),
        $provider
    ))->findOrCreateInbound($instance, '12345678901234@lid', 'Leonardo');

    expect($resolved->is($conversation))->toBeTrue()
        ->and(Conversation::query()->count())->toBe(1);
});

it('stores received media on the private disk without exposing provider data', function () {
    Storage::fake('private');
    config()->set('sensitive-data.storage.disk', 'private');

    $instance = inboundTestInstance();
    $identity = app(RemoteIdentityService::class);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'person_id' => 10,
        'remote_jid' => '5511999999999',
        'remote_jid_key' => $identity->key('5511999999999'),
        'status' => 'open',
    ]);
    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'provider_message_id' => 'provider-message-id',
        'provider_message_key' => hash('sha256', 'provider-message-id'),
        'direction' => 'incoming',
        'type' => 'image',
        'status' => 'received',
        'source' => 'openwa',
        'metadata' => [
            'chat_id' => '12345678901234@lid',
            'has_media' => true,
            'media_status' => 'queued',
        ],
    ]);
    $image = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    $provider = mock(MessagingProvider::class);
    $provider->shouldReceive('downloadMedia')
        ->once()
        ->withArgs(fn (Instance $value, string $chatId, string $messageId) => $value->is($instance)
            && $chatId === '12345678901234@lid'
            && $messageId === 'provider-message-id')
        ->andReturn($image);

    (new DownloadMessageMedia($message->id))->handle(
        $provider,
        app(SensitiveFileService::class)
    );

    $message->refresh();
    $path = data_get($message->metadata, 'media_path');

    expect($message->mediaIsStored())->toBeTrue()
        ->and(data_get($message->metadata, 'media_mime'))->toBe('image/png')
        ->and(data_get($message->metadata, 'media_size'))->toBe(strlen($image))
        ->and($path)->toStartWith("topweb-chat/{$conversation->id}/")
        ->and(Storage::disk('private')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});
