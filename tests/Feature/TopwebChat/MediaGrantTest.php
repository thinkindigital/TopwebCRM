<?php

// D2: comercial sem can_view PODE ENVIAR anexo (operacao de vendas), mas NAO
// VE anexos (nem enviados nem recebidos). Ambos veem o ID do arquivo; so
// quem tem o grant ve nome/download. Download segue exigindo o grant.

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
    foreach (['activities', 'topweb_chat_attendances', 'topweb_chat_messages', 'topweb_chat_internal_notes', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'core_config'] as $table) {
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

    Schema::create('topweb_chat_internal_notes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('user_id')->nullable();
        $table->text('content');
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

function grantContext(): array
{
    $role = Role::query()->create([
        'name' => 'Comercial', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view', 'topweb_chat.inbox.send', 'dashboard'],
    ]);
    $agent = User::query()->create([
        'name' => 'Comercial', 'email' => 'comercial@example.com',
        'role_id' => $role->id, 'status' => true, 'can_view_sensitive_data' => false,
    ]);
    $instance = Instance::query()->create([
        'name' => 'Grant', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => true,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);

    return [$agent, $conversation];
}

function storedMediaMessage(Conversation $conversation, User $user, string $name = 'proposta-alto-padrao.jpg'): Message
{
    Storage::disk('private')->put('topweb-chat/outbound/fixture.jpg', 'bytes-da-proposta');

    return Message::query()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'operation_key' => (string) Str::uuid(),
        'direction' => 'outgoing',
        'type' => 'image',
        'status' => 'sent',
        'source' => 'topweb_chat',
        'sent_at' => now(),
        'metadata' => [
            'has_media' => true,
            'media_status' => 'stored',
            'media_path' => 'topweb-chat/outbound/fixture.jpg',
            'media_mime' => 'image/jpeg',
            'media_original_name' => $name,
        ],
    ]);
}

it('allows single upload without the sensitive-data grant', function () {
    [$agent, $conversation] = grantContext();
    $this->actingAs($agent, 'user');

    $response = $this->postJson(route('admin.topweb_chat.messages.store', $conversation), [
        'media' => UploadedFile::fake()->image('proposta.jpg'),
        'operation_key' => (string) Str::uuid(),
    ]);

    $response->assertStatus(202);
    expect(Message::query()->count())->toBe(1);
    expect(Storage::disk('private')->allFiles())->not->toBeEmpty();
});

it('allows batch uploads without the sensitive-data grant', function () {
    [$agent, $conversation] = grantContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $response = $this->postJson(route('admin.topweb_chat.messages.batch', $conversation), [
        'attachments' => [
            ['file' => UploadedFile::fake()->image('a.jpg'), 'operation_key' => (string) Str::uuid()],
        ],
        '_token' => csrf_token(),
    ]);

    $response->assertStatus(202);
    expect(Message::query()->count())->toBe(1);
});

it('allows media retry without the sensitive-data grant', function () {
    [$agent, $conversation] = grantContext();
    $message = storedMediaMessage($conversation, $agent);
    $message->forceFill([
        'status' => 'failed',
        'failed_at' => now(),
        'last_error' => 'provider_instance_not_connected',
        'provider_message_id' => null,
    ])->save();
    $this->actingAs($agent, 'user');

    $this->postJson(route('admin.topweb_chat.messages.retry', [$conversation, $message]))
        ->assertStatus(202);

    expect($message->fresh()->status)->toBe('queued');
});

it('shows the file ID but hides name and bytes without the grant', function () {
    [$agent, $conversation] = grantContext();
    $message = storedMediaMessage($conversation, $agent, 'proposta-secreta.jpg');
    $this->actingAs($agent, 'user');

    // O middleware de locale resolve 'en' sem core_config semeado; o ID é
    // visível em qualquer idioma (aqui 'File #'; pt-BR validado abaixo).
    $response = $this->get(route('admin.topweb_chat.messages.index', $conversation).'?fragment=timeline');

    $response->assertOk();
    $response->assertSee('File #'.$message->id, false);
    $response->assertDontSee('proposta-secreta.jpg', false);
    $response->assertDontSee(route('admin.topweb_chat.messages.media', [$conversation, $message]), false);

    app()->setLocale('pt_BR');
    expect(trans('topweb_chat::app.messages.file_id_label', ['id' => $message->id]))
        ->toBe('Arquivo #'.$message->id);
});

it('keeps media download forbidden without the grant', function () {
    [$agent, $conversation] = grantContext();
    $message = storedMediaMessage($conversation, $agent);
    $this->actingAs($agent, 'user');

    $this->get(route('admin.topweb_chat.messages.media', [$conversation, $message]))
        ->assertForbidden();
});

it('serves the original filename to granted users', function () {
    [$agent, $conversation] = grantContext();
    $agent->forceFill(['can_view_sensitive_data' => true])->save();
    $message = storedMediaMessage($conversation, $agent, 'proposta-alto-padrao.jpg');
    $this->actingAs($agent->fresh(), 'user');

    $response = $this->get(route('admin.topweb_chat.messages.media', [$conversation, $message]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('proposta-alto-padrao.jpg');
});
