<?php

// V-01: fila operacional com itens cegos (A3), contadores honestos e claim.

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Webkul\Contact\Models\Person;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    config()->set('app.debug', false);

    foreach (['topweb_chat_messages', 'topweb_chat_conversations', 'topweb_chat_instances', 'persons', 'users', 'roles', 'activities', 'core_config'] as $table) {
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

    Schema::create('persons', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->json('emails')->nullable();
        $table->unsignedInteger('user_id')->nullable();
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
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->text('remote_jid');
        $table->char('remote_jid_key', 64);
        $table->string('status')->default('open');
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamp('last_message_at')->nullable();
        $table->timestamps();
    });

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });

    Schema::create('activities', function (Blueprint $table) {
        $table->increments('id');
        $table->string('type')->nullable();
        $table->string('title')->nullable();
        $table->boolean('is_done')->default(false);
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    touch(storage_path('installed'));
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

function queueContext(): array
{
    $adminRole = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $agentRole = Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat.inbox', 'topweb_chat.inbox.view', 'topweb_chat.inbox.assign'],
    ]);
    $admin = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'role_id' => $adminRole->id, 'status' => true,
    ]);
    $agent = User::query()->create([
        'name' => 'Agente', 'email' => 'agente@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);
    $instance = Instance::query()->create([
        'name' => 'Fila', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => true,
    ]);
    $person = Person::query()->create(['name' => 'Leonardo da Vinci']);
    $unassigned = Conversation::query()->create([
        'instance_id' => $instance->id, 'person_id' => $person->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
        'last_message_at' => now()->subMinutes(12),
    ]);

    return compact('admin', 'agent', 'unassigned');
}

it('shows queue counters in scope', function () {
    ['agent' => $agent] = queueContext();
    $this->actingAs($agent, 'user');

    $this->get(route('admin.topweb_chat.index', ['queue' => 'unassigned']))
        ->assertOk()
        ->assertSee('topweb-chat-queue-count', false);
});

it('hides identity of unassigned conversations from agents', function () {
    ['agent' => $agent] = queueContext();
    $this->actingAs($agent, 'user');

    $this->get(route('admin.topweb_chat.index', ['queue' => 'unassigned']))
        ->assertOk()
        ->assertDontSee('Leonardo da Vinci', false)
        ->assertSee('topweb-chat-blind-item', false);
});

it('keeps unassigned conversations identified for admins', function () {
    ['admin' => $admin] = queueContext();
    $this->actingAs($admin, 'user');

    $this->get(route('admin.topweb_chat.index', ['queue' => 'unassigned']))
        ->assertOk()
        ->assertSee('Leonardo da Vinci', false);
});

it('lets an agent claim from the blind queue', function () {
    ['agent' => $agent, 'unassigned' => $unassigned] = queueContext();
    $this->actingAs($agent, 'user');

    $this->put(route('admin.topweb_chat.assignment.update', $unassigned), [
        'assigned_user_id' => $agent->id,
    ])->assertRedirect();

    expect($unassigned->fresh()->assigned_user_id)->toEqual($agent->id);
});
