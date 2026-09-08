<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\TopwebChat\Jobs\SendMessage;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Services\MessageService;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['activities', 'topweb_chat_attendances', 'topweb_chat_messages', 'topweb_chat_conversations', 'topweb_chat_instances', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->unsignedInteger('role_id')->nullable();
        $table->boolean('status')->default(true);
        $table->string('view_permission')->default('individual');
        $table->boolean('can_view_sensitive_data')->default(false);
        $table->timestamps();
    });

    Schema::create('topweb_chat_instances', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('provider');
        $table->uuid('session_uuid')->nullable();
        $table->text('token')->nullable();
        $table->text('webhook_secret')->nullable();
        $table->string('base_url')->nullable();
        $table->string('status')->nullable();
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });

    Schema::create('topweb_chat_conversations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('instance_id');
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('lead_id')->nullable();
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->unsignedInteger('assigned_group_id')->nullable();
        $table->text('remote_jid');
        $table->char('remote_jid_key', 64);
        $table->string('status')->default('open');
        $table->string('priority')->default('normal');
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamp('last_message_at')->nullable();
        $table->timestamp('history_cursor_at')->nullable();
        $table->timestamp('history_backfilled_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('topweb_chat_messages', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('user_id')->nullable();
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

    Schema::create('topweb_chat_attendances', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('activity_id');
        $table->unsignedInteger('sequence');
        $table->unsignedBigInteger('opened_by_message_id')->nullable();
        $table->unsignedBigInteger('last_message_id')->nullable();
        $table->timestamp('opened_at');
        $table->timestamp('last_real_message_at');
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
        $table->unique(['conversation_id', 'sequence']);
    });

    Schema::create('activities', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->string('type');
        $table->text('comment')->nullable();
        $table->json('additional')->nullable();
        $table->dateTime('schedule_from')->nullable();
        $table->dateTime('schedule_to')->nullable();
        $table->boolean('is_done')->default(false);
        $table->unsignedInteger('user_id');
        $table->timestamps();
    });

    Storage::fake('private');
    Queue::fake();
});

function mediaQueueContext(): array
{
    $user = User::query()->create(['name' => 'Agente', 'email' => 'agente@example.com']);
    $instance = Instance::query()->create([
        'name' => 'E2E',
        'provider' => 'openwa',
        'status' => 'ready',
        'enabled' => true,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);

    return [$user, $conversation];
}

it('queues outbound media on the private disk', function () {
    [$user, $conversation] = mediaQueueContext();

    $message = app(MessageService::class)->queueMedia(
        $conversation,
        $user,
        UploadedFile::fake()->image('foto.jpg'),
        'legenda',
        (string) Str::uuid(),
    );

    expect($message->status)->toBe('queued')
        ->and($message->type)->toBe('image')
        ->and($message->content)->toBe('legenda')
        ->and($message->direction)->toBe('outgoing');

    $path = data_get($message->metadata, 'media_path');
    expect($path)->not->toBeNull();
    Storage::disk('private')->assertExists($path);

    Queue::assertPushed(SendMessage::class);
});

it('rejects oversized and unsafe outbound media', function () {
    [$user, $conversation] = mediaQueueContext();
    config()->set('topweb-chat.openwa.media_max_bytes', 10);

    expect(fn () => app(MessageService::class)->queueMedia(
        $conversation, $user,
        UploadedFile::fake()->image('grande.jpg'),
        null, (string) Str::uuid(),
    ))->toThrow(DomainException::class);

    $svg = UploadedFile::fake()->createWithContent('vetor.svg', '<svg></svg>');
    expect(fn () => app(MessageService::class)->queueMedia(
        $conversation, $user, $svg, null, (string) Str::uuid(),
    ))->toThrow(DomainException::class);

    expect(Message::query()->count())->toBe(0);
    expect(Storage::disk('private')->allFiles())->toBeEmpty();
});
