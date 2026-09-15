<?php

// D04 (#15): Lead.user_id como autoridade operacional da conversa vinculada.
// RED: estes testes falham no CURRENT (autoridade por assigned_user_id).
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Repositories\ConversationRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    config()->set('app.debug', false);
    Cache::shouldReceive('has')->andReturnFalse();
    Cache::shouldReceive('put')->andReturnTrue();

    foreach (['topweb_chat_messages', 'topweb_chat_internal_notes', 'topweb_chat_conversations', 'topweb_chat_instances', 'leads', 'lead_pipelines', 'lead_pipeline_stages', 'activities', 'lead_activities', 'topweb_chat_attendances', 'users', 'roles', 'core_config', 'attributes'] as $table) {
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

    Schema::create('leads', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('lead_pipeline_id')->nullable();
        $table->unsignedInteger('lead_pipeline_stage_id')->nullable();
        $table->timestamps();
    });

    // D04: o show com Lead renderiza pipeline, next action e recentes.
    Schema::create('lead_pipelines', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->unsignedInteger('rotten_days')->default(0);
        $table->boolean('is_default')->default(false);
        $table->timestamps();
    });

    Schema::create('lead_pipeline_stages', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->nullable();
        $table->string('name');
        $table->unsignedInteger('probability')->default(0);
        $table->unsignedInteger('sort_order')->default(0);
        $table->unsignedInteger('lead_pipeline_id')->nullable();
    });

    Schema::create('activities', function (Blueprint $table) {
        $table->increments('id');
        $table->string('type')->nullable();
        $table->string('title')->nullable();
        $table->text('comment')->nullable();
        $table->json('additional')->nullable();
        $table->datetime('schedule_from')->nullable();
        $table->datetime('schedule_to')->nullable();
        $table->boolean('is_done')->default(false);
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('lead_activities', function (Blueprint $table) {
        $table->unsignedInteger('activity_id');
        $table->unsignedInteger('lead_id');
    });

    Schema::create('topweb_chat_attendances', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id')->nullable();
        $table->unsignedInteger('activity_id')->nullable();
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

function ownershipContext(): array
{
    $adminRole = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $agentRole = Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view', 'topweb_chat.inbox.assign', 'dashboard'],
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
    $leadId = DB::table('leads')->insertGetId([
        'title' => 'Lead D04', 'person_id' => null, 'user_id' => $owner->id,
        'lead_pipeline_id' => DB::table('lead_pipelines')->insertGetId([
            'name' => 'Pipeline D04', 'rotten_days' => 0, 'is_default' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'lead_pipeline_stage_id' => DB::table('lead_pipeline_stages')->insertGetId([
            'code' => 'e2e', 'name' => 'E2E Etapa', 'probability' => 0, 'sort_order' => 0,
            'lead_pipeline_id' => DB::getPdo()->lastInsertId(),
        ]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $linked = Conversation::query()->create([
        'instance_id' => $instance->id,
        'lead_id' => $leadId,
        'assigned_user_id' => null,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);
    $divergent = Conversation::query()->create([
        'instance_id' => $instance->id,
        'lead_id' => $leadId,
        'assigned_user_id' => $stranger->id,
        'remote_jid' => '5511888888888@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511888888888@s.whatsapp.net'),
    ]);
    $noLead = Conversation::query()->create([
        'instance_id' => $instance->id,
        'assigned_user_id' => null,
        'remote_jid' => '5511777777777@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511777777777@s.whatsapp.net'),
    ]);

    return compact('admin', 'owner', 'stranger', 'leadId', 'linked', 'divergent', 'noLead');
}

it('lets the lead owner view the linked conversation without projection', function () {
    ['owner' => $owner, 'linked' => $linked] = ownershipContext();
    $this->actingAs($owner, 'user');

    $this->get(route('admin.topweb_chat.show', $linked))->assertOk();
    $this->getJson(route('admin.topweb_chat.messages.index', $linked))->assertOk();
});

it('blocks strangers on lead-linked conversations', function () {
    ['stranger' => $stranger, 'linked' => $linked] = ownershipContext();
    $this->actingAs($stranger, 'user');

    $this->get(route('admin.topweb_chat.show', $linked))->assertForbidden();
    $this->getJson(route('admin.topweb_chat.messages.index', $linked))->assertForbidden();
});

it('enforces zero divergence between projection and lead ownership', function () {
    ['owner' => $owner, 'stranger' => $stranger, 'divergent' => $divergent] = ownershipContext();

    // Assignee sem ownership nao herda acesso pela projecao.
    $this->actingAs($stranger, 'user');
    $this->get(route('admin.topweb_chat.show', $divergent))->assertForbidden();

    // Dono mantem acesso mesmo com projecao divergente.
    $this->actingAs($owner, 'user');
    $this->get(route('admin.topweb_chat.show', $divergent))->assertOk();
});

it('syncs the projection atomically on lead transfer and revokes the ex-owner', function () {
    ['owner' => $owner, 'stranger' => $stranger, 'leadId' => $leadId, 'linked' => $linked] = ownershipContext();

    // Caminho real: update via model dispara o observer D04 (e o LogsActivity).
    $lead = Lead::query()->findOrFail($leadId);
    $lead->update(['user_id' => $stranger->id]);

    expect($linked->fresh()->assigned_user_id)->toBe($stranger->id);

    $this->actingAs($owner, 'user');
    $this->get(route('admin.topweb_chat.show', $linked))->assertForbidden();

    $this->actingAs($stranger, 'user');
    $this->get(route('admin.topweb_chat.show', $linked))->assertOk();
});

it('rejects assigning a linked conversation to a non-owner', function () {
    ['stranger' => $stranger, 'linked' => $linked] = ownershipContext();
    $this->actingAs($stranger, 'user');
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    // Negacao no assignment devolve 302 com erro (contrato V-07), sem mudar nada.
    $this->put(
        route('admin.topweb_chat.assignment.update', $linked),
        ['assigned_user_id' => $stranger->id]
    )->assertRedirect()->assertSessionHas('error');

    expect($linked->fresh()->assigned_user_id)->toBeNull();
});

it('lets admins assign linked conversations to the lead owner', function () {
    ['admin' => $admin, 'owner' => $owner, 'linked' => $linked] = ownershipContext();
    $this->actingAs($admin, 'user');
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    $this->put(
        route('admin.topweb_chat.assignment.update', $linked),
        ['assigned_user_id' => $owner->id]
    )->assertRedirect();

    expect($linked->fresh()->assigned_user_id)->toBe($owner->id);
});

it('scopes queues by lead ownership', function () {
    ['owner' => $owner, 'stranger' => $stranger, 'linked' => $linked, 'noLead' => $noLead] = ownershipContext();
    $repository = app(ConversationRepository::class);

    expect($repository->accessibleQuery($owner, 'mine')->pluck('id')->all())
        ->toContain($linked->id)
        ->not->toContain($noLead->id);

    expect($repository->accessibleQuery($stranger, 'mine')->pluck('id')->all())
        ->not->toContain($linked->id);

    expect($repository->accessibleQuery($stranger, 'unassigned')->pluck('id')->all())
        ->toContain($noLead->id)
        ->not->toContain($linked->id);
});

it('preserves legacy self-claim on unassigned conversations without lead', function () {
    ['stranger' => $stranger, 'noLead' => $noLead] = ownershipContext();
    $this->actingAs($stranger, 'user');

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    $response = $this->put(
        route('admin.topweb_chat.assignment.update', $noLead),
        ['assigned_user_id' => $stranger->id]
    );
    $response->assertRedirect();

    expect($noLead->fresh()->assigned_user_id)->toBe($stranger->id);
});
