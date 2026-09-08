<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['activities', 'topweb_chat_attendances', 'topweb_chat_messages', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'core_config'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('description')->nullable();
        $table->string('permission_type');
        $table->text('permissions')->nullable();
        $table->timestamps();
    });

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

    touch(storage_path('installed'));
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

function mediaStoreContext(): array
{
    $role = Role::query()->create([
        'name' => 'Admin', 'permission_type' => 'all', 'permissions' => ['topweb_chat.inbox.send'],
    ]);
    $user = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'role_id' => $role->id, 'status' => true,
    ]);
    $instance = Instance::query()->create([
        'name' => 'E2E', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => true,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);

    return [$user, $conversation];
}

it('stores outbound media with the same authorization as text', function () {
    [$user, $conversation] = mediaStoreContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($user, 'user');
    $token = csrf_token();

    $response = $this->postJson(
        route('admin.topweb_chat.messages.store', $conversation),
        [
            'media' => UploadedFile::fake()->image('foto.jpg'),
            'caption' => 'olha isso',
            'operation_key' => (string) Str::uuid(),
            '_token' => $token,
        ]
    );

    $response->assertStatus(202);
    expect($response->json('message.type'))->toBe('image')
        ->and($response->json('message.status'))->toBe('queued');
});

it('rejects media without permission or without file', function () {
    [$user, $conversation] = mediaStoreContext();

    $role = Role::query()->create([
        'name' => 'Sem chat', 'permission_type' => 'custom', 'permissions' => ['dashboard'],
    ]);
    $plain = User::query()->create([
        'name' => 'Plano', 'email' => 'plano@example.com', 'role_id' => $role->id, 'status' => true,
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($plain, 'user');
    $this->get('/up')->assertOk();
    $token = csrf_token();

    // Sem inbox.send: o middleware Bouncer nega com 401 antes do controller.
    $this->postJson(
        route('admin.topweb_chat.messages.store', $conversation),
        ['media' => UploadedFile::fake()->image('foto.jpg'), 'operation_key' => (string) Str::uuid(), '_token' => $token]
    )->assertUnauthorized();

    $this->actingAs($user, 'user');

    $this->postJson(
        route('admin.topweb_chat.messages.store', $conversation),
        ['operation_key' => (string) Str::uuid(), '_token' => $token]
    )->assertStatus(422);
});
