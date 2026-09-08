<?php

// Matriz negativa de acesso a conversas (#23, slice 1).
// Documenta o comportamento atual por perfil; divergências vs
// SECURITY_RULES §14 / PRODUCT_RULES §11.3 são achados, não auto-fix.
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    // Matriz valida comportamento de produção (debug desligado: handler de erro real).
    config()->set('app.debug', false);

    foreach (['topweb_chat_messages', 'topweb_chat_internal_notes', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'core_config', 'attributes'] as $table) {
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

    Schema::create('topweb_chat_internal_notes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('user_id')->nullable();
        $table->text('content');
        $table->timestamps();
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

    touch(storage_path('installed'));
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

function accessMatrixContext(): array
{
    $adminRole = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $agentRole = Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat.inbox.view', 'dashboard'],
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
    $owned = Conversation::query()->create([
        'instance_id' => $instance->id,
        'assigned_user_id' => $owner->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);
    $unassigned = Conversation::query()->create([
        'instance_id' => $instance->id,
        'remote_jid' => '5511888888888@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511888888888@s.whatsapp.net'),
    ]);

    return compact('admin', 'owner', 'stranger', 'owned', 'unassigned');
}

it('grants admins everywhere and owners on their own conversations', function () {
    ['admin' => $admin, 'owner' => $owner, 'owned' => $owned] = accessMatrixContext();

    $this->actingAs($admin, 'user');
    $this->get(route('admin.topweb_chat.show', $owned))->assertOk();
    $this->getJson(route('admin.topweb_chat.messages.index', $owned))->assertOk();

    $this->actingAs($owner, 'user');
    $this->get(route('admin.topweb_chat.show', $owned))->assertOk();
    $this->getJson(route('admin.topweb_chat.messages.index', $owned))->assertOk();
});

it('blocks strangers on conversations owned by someone else', function () {
    ['stranger' => $stranger, 'owned' => $owned] = accessMatrixContext();
    $this->actingAs($stranger, 'user');

    $this->get(route('admin.topweb_chat.show', $owned))->assertForbidden();
    $this->getJson(route('admin.topweb_chat.messages.index', $owned))->assertForbidden();
});

it('documents unassigned conversation access for common users', function () {
    ['stranger' => $stranger, 'unassigned' => $unassigned, 'admin' => $admin] = accessMatrixContext();

    $this->actingAs($admin, 'user');
    $this->get(route('admin.topweb_chat.show', $unassigned))->assertOk();

    // Comportamento atual: usuário comum enxerga conversa sem responsável.
    // Diverge de SECURITY_RULES §14.2 / PRODUCT_RULES §11.3 — achado #23,
    // sem auto-fix: mudar quebra o fluxo de claim (assignment).
    $this->actingAs($stranger, 'user');
    $this->get(route('admin.topweb_chat.show', $unassigned))->assertOk();
});
