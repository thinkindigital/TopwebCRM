<?php

// V-07: claim/transferência explícitos com feedback em perda de corrida.

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    config()->set('app.debug', false);

    foreach (['topweb_chat_messages', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'activities', 'person_activities', 'attributes', 'core_config'] as $table) {
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

    Schema::create('activities', function (Blueprint $table) {
        $table->increments('id');
        $table->string('type')->nullable();
        $table->string('title')->nullable();
        $table->boolean('is_done')->default(false);
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('person_activities', function (Blueprint $table) {
        $table->unsignedInteger('activity_id');
        $table->unsignedInteger('person_id');
    });

    Schema::create('attributes', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->nullable();
        $table->string('name')->nullable();
        $table->string('type')->nullable();
        $table->string('entity_type')->nullable();
        $table->boolean('quick_add')->default(false);
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
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->text('remote_jid');
        $table->char('remote_jid_key', 64);
        $table->string('status')->default('open');
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

function claimContext(): array
{
    $agentRole = Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view', 'topweb_chat.inbox.assign', 'dashboard'],
    ]);
    $alice = User::query()->create([
        'name' => 'Alice', 'email' => 'alice@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);
    $bruno = User::query()->create([
        'name' => 'Bruno', 'email' => 'bruno@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);
    $instance = Instance::query()->create([
        'name' => 'Claim', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => true,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id, 'assigned_user_id' => $alice->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);

    return compact('alice', 'bruno', 'conversation');
}

it('tells who won when a claim loses the race', function () {
    ['bruno' => $bruno, 'conversation' => $conversation] = claimContext();
    $this->actingAs($bruno, 'user');

    $response = $this->put(route('admin.topweb_chat.assignment.update', $conversation), [
        'assigned_user_id' => $bruno->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('Alice');
    expect($conversation->fresh()->assigned_user_id)->not->toEqual($bruno->id);
});

it('lets the owner release back to the blind queue', function () {
    ['alice' => $alice, 'conversation' => $conversation] = claimContext();
    $this->actingAs($alice, 'user');

    $this->put(route('admin.topweb_chat.assignment.update', $conversation), [
        'assigned_user_id' => null,
    ])->assertRedirect();

    expect($conversation->fresh()->assigned_user_id)->toBeNull();
});
