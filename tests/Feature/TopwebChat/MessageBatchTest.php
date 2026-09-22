<?php

// E14-R2 S5: batch real de múltiplos attachments.

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\Message;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['activities', 'topweb_chat_attendances', 'topweb_chat_messages', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'core_config'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('roles', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
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
        $table->string('provider')->default('openwa');
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
        $table->text('remote_jid');
        $table->char('remote_jid_key', 64);
        $table->string('status')->default('open');
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamp('last_message_at')->nullable();
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

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });

    Storage::fake('private');
    Queue::fake();

    touch(storage_path('installed'));
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

function batchContext(): array
{
    $role = Role::query()->create([
        'name' => 'Admin', 'permission_type' => 'all', 'permissions' => ['topweb_chat.inbox.send'],
    ]);
    $user = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'role_id' => $role->id,
        'status' => true, 'can_view_sensitive_data' => true,
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

it('queues one message per file with independent operation keys plus a single text', function () {
    [$user, $conversation] = batchContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($user, 'user');
    $token = csrf_token();
    $firstKey = (string) Str::uuid();
    $secondKey = (string) Str::uuid();
    $textKey = (string) Str::uuid();

    $response = $this->postJson(
        route('admin.topweb_chat.messages.batch', $conversation),
        [
            'attachments' => [
                ['file' => UploadedFile::fake()->image('a.jpg'), 'operation_key' => $firstKey],
                ['file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'), 'operation_key' => $secondKey],
            ],
            'content' => 'segue o material',
            'content_operation_key' => $textKey,
            '_token' => $token,
        ]
    );

    $response->assertStatus(202);

    $keys = Message::query()->pluck('operation_key')->all();
    expect($keys)->toHaveCount(3)
        ->and($keys)->toContain($firstKey, $secondKey, $textKey);

    $texts = Message::query()->where('type', 'text')->get();
    expect($texts)->toHaveCount(1)
        ->and($texts->first()->content)->toBe('segue o material');

    expect(Message::query()->where('type', '!=', 'text')->whereNotNull('content')->count())->toBe(0);
});

it('does not duplicate messages on batch retry', function () {
    [$user, $conversation] = batchContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($user, 'user');
    $token = csrf_token();
    $firstKey = (string) Str::uuid();
    $secondKey = (string) Str::uuid();
    $payload = [
        'attachments' => [
            ['file' => UploadedFile::fake()->image('a.jpg'), 'operation_key' => $firstKey],
            ['file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'), 'operation_key' => $secondKey],
        ],
        '_token' => $token,
    ];

    $first = $this->postJson(route('admin.topweb_chat.messages.batch', $conversation), $payload);
    $first->assertStatus(202);

    $second = $this->postJson(route('admin.topweb_chat.messages.batch', $conversation), $payload);
    $second->assertStatus(202);

    expect(Message::query()->count())->toBe(2);
    expect(collect($second->json('messages'))->pluck('duplicate')->all())->toEqual([true, true]);
});

it('accepts the newly aligned document types', function () {
    [$user, $conversation] = batchContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($user, 'user');

    $response = $this->postJson(
        route('admin.topweb_chat.messages.batch', $conversation),
        [
            'attachments' => [
                ['file' => UploadedFile::fake()->create('a.txt', 10, 'text/plain'), 'operation_key' => (string) Str::uuid()],
                ['file' => UploadedFile::fake()->create('b.xls', 100, 'application/vnd.ms-excel'), 'operation_key' => (string) Str::uuid()],
                ['file' => UploadedFile::fake()->create('c.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), 'operation_key' => (string) Str::uuid()],
            ],
            '_token' => csrf_token(),
        ]
    );

    $response->assertStatus(202);
    expect($response->json('messages'))->toHaveCount(3)
        ->and($response->json('rejected'))->toBeEmpty();
});

it('enforces the configured batch limits', function () {
    [$user, $conversation] = batchContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($user, 'user');
    $token = csrf_token();

    config()->set('topweb-chat.batch.max_files', 2);

    $this->postJson(
        route('admin.topweb_chat.messages.batch', $conversation),
        [
            'attachments' => [
                ['file' => UploadedFile::fake()->image('a.jpg'), 'operation_key' => (string) Str::uuid()],
                ['file' => UploadedFile::fake()->image('b.jpg'), 'operation_key' => (string) Str::uuid()],
                ['file' => UploadedFile::fake()->image('c.jpg'), 'operation_key' => (string) Str::uuid()],
            ],
            '_token' => $token,
        ]
    )->assertStatus(422);

    config()->set('topweb-chat.batch.max_files', 10);
    config()->set('topweb-chat.batch.max_bytes', 10);

    $this->postJson(
        route('admin.topweb_chat.messages.batch', $conversation),
        [
            'attachments' => [
                ['file' => UploadedFile::fake()->image('a.jpg'), 'operation_key' => (string) Str::uuid()],
            ],
            '_token' => $token,
        ]
    )->assertStatus(422);

    expect(Message::query()->count())->toBe(0);
});

it('accepts valid files and reports invalid ones per item', function () {
    [$user, $conversation] = batchContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($user, 'user');
    $token = csrf_token();
    $validKey = (string) Str::uuid();
    $invalidKey = (string) Str::uuid();

    $response = $this->postJson(
        route('admin.topweb_chat.messages.batch', $conversation),
        [
            'attachments' => [
                ['file' => UploadedFile::fake()->image('a.jpg'), 'operation_key' => $validKey],
                ['file' => UploadedFile::fake()->create('evil.exe', 100, 'application/x-msdownload'), 'operation_key' => $invalidKey],
            ],
            '_token' => $token,
        ]
    );

    $response->assertStatus(202);

    expect($response->json('messages'))->toHaveCount(1)
        ->and($response->json('messages.0.operation_key'))->toBe($validKey)
        ->and($response->json('rejected'))->toHaveCount(1)
        ->and($response->json('rejected.0.operation_key'))->toBe($invalidKey)
        ->and($response->json('rejected.0.error_code'))->toBe('FIL-1001');
    expect(Message::query()->count())->toBe(1);
});

it('rejects the whole batch when the instance is not ready', function () {
    [$user, $conversation] = batchContext();
    $conversation->instance->forceFill(['status' => 'disconnected'])->save();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($user, 'user');

    $this->postJson(
        route('admin.topweb_chat.messages.batch', $conversation),
        [
            'attachments' => [
                ['file' => UploadedFile::fake()->image('a.jpg'), 'operation_key' => (string) Str::uuid()],
            ],
            '_token' => csrf_token(),
        ]
    )->assertStatus(503)
        ->assertJsonPath('error.code', 'API-7001');

    expect(Message::query()->count())->toBe(0);
    expect(Storage::disk('private')->allFiles())->toBeEmpty();
});

it('refuses batch uploads without the send permission', function () {
    [$admin, $conversation] = batchContext();
    $role = Role::query()->create([
        'name' => 'Sem envio', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view', 'dashboard'],
    ]);
    $plain = User::query()->create([
        'name' => 'Sem envio', 'email' => 'semenvio@example.com',
        'role_id' => $role->id, 'status' => true, 'can_view_sensitive_data' => true,
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($plain, 'user');

    $this->postJson(
        route('admin.topweb_chat.messages.batch', $conversation),
        [
            'attachments' => [
                ['file' => UploadedFile::fake()->image('a.jpg'), 'operation_key' => (string) Str::uuid()],
            ],
            '_token' => csrf_token(),
        ]
    )->assertForbidden();

    expect(Message::query()->count())->toBe(0);
});

it('refuses batch uploads without the sensitive-data grant', function () {
    [$admin, $conversation] = batchContext();
    $plain = User::query()->create([
        'name' => 'Sem grant', 'email' => 'semgrant@example.com',
        'role_id' => $admin->role_id, 'status' => true,
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($plain, 'user');

    $this->postJson(
        route('admin.topweb_chat.messages.batch', $conversation),
        [
            'attachments' => [
                ['file' => UploadedFile::fake()->image('a.jpg'), 'operation_key' => (string) Str::uuid()],
            ],
            '_token' => csrf_token(),
        ]
    )->assertForbidden();

    expect(Message::query()->count())->toBe(0);
    expect(Storage::disk('private')->allFiles())->toBeEmpty();
});
