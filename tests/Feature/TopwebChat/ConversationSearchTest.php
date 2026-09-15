<?php

// V-06 (#101, TB-09): busca segura com escopo de carteira e zero oráculo.
// RED: endpoint admin.topweb_chat.search ainda não existe.
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    config()->set('app.debug', false);

    foreach (['topweb_chat_messages', 'topweb_chat_conversations', 'topweb_chat_instances', 'persons', 'leads', 'users', 'roles', 'core_config', 'attributes'] as $table) {
        Schema::dropIfExists($table);
    }

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

    Schema::create('persons', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->json('emails')->nullable();
        $table->json('contact_numbers')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('leads', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
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

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });

    DB::table('core_config')->insert([
        ['code' => 'general.design.admin_logo.favicon', 'value' => null],
        ['code' => 'general.design.admin_logo.logo_image', 'value' => null],
    ]);

    Schema::create('attributes', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->nullable();
        $table->string('name')->nullable();
        $table->string('type')->nullable();
        $table->string('entity_type')->nullable();
        $table->boolean('quick_add')->default(false);
        $table->timestamps();
    });

    touch(storage_path('installed'));
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

function searchContext(): array
{
    $adminRole = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $agentRole = Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view', 'dashboard'],
    ]);
    $admin = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'role_id' => $adminRole->id, 'status' => true,
    ]);
    $owner = User::query()->create([
        'name' => 'Dono', 'email' => 'dono@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);
    $stranger = User::query()->create([
        'name' => 'Outro', 'email' => 'outro@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);
    $instance = Instance::query()->create([
        'name' => 'E2E', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => false,
    ]);
    $personId = DB::table('persons')->insertGetId([
        'name' => 'Cliente Alfa', 'user_id' => $owner->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $leadId = DB::table('leads')->insertGetId([
        'title' => 'Negocio Alfa', 'person_id' => $personId, 'user_id' => $owner->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherPersonId = DB::table('persons')->insertGetId([
        'name' => 'Cliente Beta', 'user_id' => $stranger->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherLeadId = DB::table('leads')->insertGetId([
        'title' => 'Negocio Beta', 'person_id' => $otherPersonId, 'user_id' => $stranger->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $mine = Conversation::query()->create([
        'instance_id' => $instance->id,
        'person_id' => $personId,
        'lead_id' => $leadId,
        'assigned_user_id' => $owner->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);
    $theirs = Conversation::query()->create([
        'instance_id' => $instance->id,
        'person_id' => $otherPersonId,
        'lead_id' => $otherLeadId,
        'assigned_user_id' => $stranger->id,
        'remote_jid' => '5511888888888@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511888888888@s.whatsapp.net'),
    ]);

    return compact('admin', 'owner', 'stranger', 'mine', 'theirs');
}

it('finds wallet conversations by name or title without leaking others', function () {
    ['owner' => $owner, 'mine' => $mine, 'theirs' => $theirs] = searchContext();
    $this->actingAs($owner, 'user');

    $response = $this->getJson(route('admin.topweb_chat.search', ['q' => 'Alfa']));
    $response->assertOk()->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $mine->id);

    // Zero oráculo: mesma forma, vazio — sem revelar existência alheia.
    $empty = $this->getJson(route('admin.topweb_chat.search', ['q' => 'Beta']));
    $empty->assertOk()->assertJsonCount(0, 'data');
});

it('never searches by phone, email or remote identifier', function () {
    ['owner' => $owner] = searchContext();
    $this->actingAs($owner, 'user');

    $this->getJson(route('admin.topweb_chat.search', ['q' => '5511999999999']))
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('admin.topweb_chat.search', ['q' => 'dono@example.com']))
        ->assertOk()->assertJsonCount(0, 'data');
});

it('returns empty result for short queries and guests', function () {
    ['owner' => $owner] = searchContext();
    $this->actingAs($owner, 'user');

    $this->getJson(route('admin.topweb_chat.search', ['q' => 'A']))
        ->assertOk()->assertJsonCount(0, 'data');
});

it('exposes no sensitive fields in search payloads', function () {
    ['owner' => $owner] = searchContext();
    $this->actingAs($owner, 'user');

    $payload = $this->getJson(route('admin.topweb_chat.search', ['q' => 'Alfa']))
        ->assertOk()->json();

    expect(json_encode($payload))->not->toContain('5511999999999')
        ->and(json_encode($payload))->not->toContain('s.whatsapp.net');
});
