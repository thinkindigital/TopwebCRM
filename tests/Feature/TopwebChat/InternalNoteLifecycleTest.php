<?php

// E14-R2 S2: ciclo de vida de notas internas (criar, timeline, excluir).

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\InternalNote;
use Webkul\TopwebChat\Models\Message;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['topweb_chat_internal_notes', 'topweb_chat_messages', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'core_config'] as $table) {
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

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });

    touch(storage_path('installed'));
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

function noteContext(): array
{
    $adminRole = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $agentRole = Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view', 'topweb_chat.inbox.notes', 'topweb_chat.inbox.notes.delete', 'dashboard'],
    ]);
    $noNotesRole = Role::query()->create([
        'name' => 'Sem notas', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view', 'dashboard'],
    ]);
    $admin = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'role_id' => $adminRole->id, 'status' => true,
    ]);
    $agent = User::query()->create([
        'name' => 'Agente', 'email' => 'agente@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);
    $outsider = User::query()->create([
        'name' => 'Fora', 'email' => 'fora@example.com',
        'role_id' => $noNotesRole->id, 'status' => true,
    ]);
    $instance = Instance::query()->create([
        'name' => 'E2E', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => false,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'assigned_user_id' => $agent->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);

    return compact('admin', 'agent', 'outsider', 'conversation');
}

it('creates a note bound to the conversation and the author', function () {
    ['agent' => $agent, 'conversation' => $conversation] = noteContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->post(
        route('admin.topweb_chat.notes.store', $conversation),
        ['content' => 'Cliente pediu retorno apos as 18h.', '_token' => csrf_token()]
    )->assertRedirect();

    $note = InternalNote::query()->first();
    expect($note->conversation_id)->toEqual($conversation->id)
        ->and($note->user_id)->toEqual($agent->id)
        ->and($note->content)->toBe('Cliente pediu retorno apos as 18h.');
});

it('lets the author delete their own note', function () {
    ['agent' => $agent, 'conversation' => $conversation] = noteContext();
    $note = InternalNote::query()->create([
        'conversation_id' => $conversation->id, 'user_id' => $agent->id,
        'content' => 'rascunho',
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->deleteJson(
        route('admin.topweb_chat.notes.destroy', [$conversation, $note]),
        ['_token' => csrf_token()]
    )->assertOk();

    expect(InternalNote::query()->count())->toBe(0);
});

it('refuses deletion by a non-author without admin scope', function () {
    ['admin' => $admin, 'conversation' => $conversation] = noteContext();
    $other = User::query()->create([
        'name' => 'Outro', 'email' => 'outro@example.com',
        'role_id' => $admin->role_id, 'status' => true,
    ]);
    $note = InternalNote::query()->create([
        'conversation_id' => $conversation->id, 'user_id' => $other->id,
        'content' => 'de outro autor',
    ]);
    $deleter = User::query()->create([
        'name' => 'Agente2', 'email' => 'agente2@example.com',
        'role_id' => Role::query()->create([
            'name' => 'Agente2', 'permission_type' => 'custom',
            'permissions' => ['topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view', 'topweb_chat.inbox.notes', 'topweb_chat.inbox.notes.delete', 'dashboard'],
        ])->id, 'status' => true,
    ]);
    $conversation->forceFill(['assigned_user_id' => $deleter->id])->save();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($deleter, 'user');

    $this->deleteJson(
        route('admin.topweb_chat.notes.destroy', [$conversation, $note]),
        ['_token' => csrf_token()]
    )->assertForbidden();

    expect(InternalNote::query()->count())->toBe(1);
});

it('lets an authorized admin delete notes from other authors', function () {
    ['admin' => $admin, 'agent' => $agent, 'conversation' => $conversation] = noteContext();
    $note = InternalNote::query()->create([
        'conversation_id' => $conversation->id, 'user_id' => $agent->id,
        'content' => 'do agente',
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($admin, 'user');

    $this->deleteJson(
        route('admin.topweb_chat.notes.destroy', [$conversation, $note]),
        ['_token' => csrf_token()]
    )->assertOk();

    expect(InternalNote::query()->count())->toBe(0);
});

it('rejects deletion of a note from another conversation', function () {
    ['agent' => $agent, 'conversation' => $conversation] = noteContext();
    $other = Conversation::query()->create([
        'instance_id' => $conversation->instance_id,
        'assigned_user_id' => $agent->id,
        'remote_jid' => '5511888888888@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511888888888@s.whatsapp.net'),
    ]);
    $note = InternalNote::query()->create([
        'conversation_id' => $other->id, 'user_id' => $agent->id,
        'content' => 'de outra conversa',
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->deleteJson(
        route('admin.topweb_chat.notes.destroy', [$conversation, $note]),
        ['_token' => csrf_token()]
    )->assertNotFound();

    expect(InternalNote::query()->count())->toBe(1);
});

it('renders notes at their chronological position in the timeline', function () {
    ['admin' => $admin, 'conversation' => $conversation] = noteContext();
    Message::query()->create([
        'conversation_id' => $conversation->id, 'direction' => 'incoming',
        'type' => 'text', 'content' => 'primeira-mensagem', 'status' => 'read',
        'source' => 'topweb_chat', 'sent_at' => now()->subMinutes(30),
    ]);
    $mid = InternalNote::query()->create([
        'conversation_id' => $conversation->id, 'user_id' => $admin->id,
        'content' => 'nota-do-meio',
    ]);
    $mid->forceFill([
        'created_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20),
    ])->save();
    Message::query()->create([
        'conversation_id' => $conversation->id, 'direction' => 'incoming',
        'type' => 'text', 'content' => 'ultima-mensagem', 'status' => 'read',
        'source' => 'topweb_chat', 'sent_at' => now()->subMinutes(10),
    ]);
    $this->actingAs($admin, 'user');

    $html = $this->get(
        route('admin.topweb_chat.messages.index', $conversation).'?fragment=timeline'
    )->getContent();

    $first = strpos($html, 'primeira-mensagem');
    $note = strpos($html, 'nota-do-meio');
    $last = strpos($html, 'ultima-mensagem');

    expect($first)->not->toBeFalse()
        ->and($note)->not->toBeFalse()
        ->and($last)->not->toBeFalse()
        ->and($first < $note && $note < $last)->toBeTrue();
});

it('drops the deleted note from the timeline fragment', function () {
    ['agent' => $agent, 'conversation' => $conversation] = noteContext();
    $note = InternalNote::query()->create([
        'conversation_id' => $conversation->id, 'user_id' => $agent->id,
        'content' => 'nota-removivel',
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $fragment = route('admin.topweb_chat.messages.index', $conversation).'?fragment=timeline';
    expect($this->get($fragment)->getContent())->toContain('nota-removivel');

    $this->deleteJson(
        route('admin.topweb_chat.notes.destroy', [$conversation, $note]),
        ['_token' => csrf_token()]
    )->assertOk();

    expect($this->get($fragment)->getContent())->not->toContain('nota-removivel');
});

it('answers note creation as JSON for inline editing', function () {
    ['agent' => $agent, 'conversation' => $conversation] = noteContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $response = $this->postJson(
        route('admin.topweb_chat.notes.store', $conversation),
        ['content' => 'nota json', '_token' => csrf_token()]
    )->assertCreated();

    expect($response->json('note_id'))->toEqual(InternalNote::query()->first()->id);
});

it('refuses note creation without the notes permission', function () {
    ['outsider' => $outsider, 'conversation' => $conversation] = noteContext();
    $mine = Conversation::query()->create([
        'instance_id' => $conversation->instance_id,
        'assigned_user_id' => $outsider->id,
        'remote_jid' => '5511888888888@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511888888888@s.whatsapp.net'),
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($outsider, 'user');

    // Sem inbox.notes: o Bouncer nega na borda da rota antes de persistir.
    $this->post(
        route('admin.topweb_chat.notes.store', $mine),
        ['content' => 'tentativa', '_token' => csrf_token()]
    )->assertUnauthorized();

    expect(InternalNote::query()->count())->toBe(0);
});
