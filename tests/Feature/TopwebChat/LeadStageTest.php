<?php

// E14-R2 S4: etapa e pipeline inline pela fronteira do TopwebChat.

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Services\LeadStageService;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['lead_pipeline_stages', 'lead_pipelines', 'leads', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'core_config', 'attributes'] as $table) {
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

    Schema::create('lead_pipelines', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->integer('rotten_days')->default(0);
        $table->boolean('is_default')->default(false);
        $table->timestamps();
    });

    Schema::create('lead_pipeline_stages', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->nullable();
        $table->string('name');
        $table->integer('probability')->default(0);
        $table->integer('sort_order')->default(0);
        $table->unsignedInteger('lead_pipeline_id');
    });

    Schema::create('leads', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('lead_pipeline_id')->nullable();
        $table->unsignedInteger('lead_pipeline_stage_id')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->date('expected_close_date')->nullable();
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

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
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

function stageContext(): array
{
    $adminRole = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $agentRole = Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => [
            'topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view',
            'topweb_chat.inbox.stage', 'leads.edit', 'dashboard',
        ],
    ]);
    $admin = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'role_id' => $adminRole->id, 'status' => true,
    ]);
    $agent = User::query()->create([
        'name' => 'Agente', 'email' => 'agente@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);
    $pipeline = Pipeline::query()->create(['name' => 'Residencial']);
    $qualification = Stage::query()->create([
        'code' => 'qualification', 'name' => 'Qualificação',
        'probability' => 20, 'sort_order' => 1, 'lead_pipeline_id' => $pipeline->id,
    ]);
    $proposal = Stage::query()->create([
        'code' => 'proposal', 'name' => 'Proposta',
        'probability' => 60, 'sort_order' => 2, 'lead_pipeline_id' => $pipeline->id,
    ]);
    $lead = Lead::withoutEvents(
        fn () => Lead::query()->create([
            'title' => 'Zeta Negocio', 'user_id' => $agent->id,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $qualification->id,
        ])
    );
    $instance = Instance::query()->create([
        'name' => 'E2E', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => false,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id, 'lead_id' => $lead->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);

    return compact('admin', 'agent', 'pipeline', 'qualification', 'proposal', 'lead', 'conversation');
}

it('moves the lead to a valid stage of the current pipeline', function () {
    ['agent' => $agent, 'proposal' => $proposal, 'lead' => $lead, 'conversation' => $conversation] = stageContext();
    Event::fake();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->put(
        route('admin.topweb_chat.lead_stage.update', $conversation),
        ['lead_pipeline_stage_id' => $proposal->id, '_token' => csrf_token()]
    )->assertRedirect();

    expect($lead->fresh()->lead_pipeline_stage_id)->toEqual($proposal->id);
    Event::assertDispatched('lead.update.before');
    Event::assertDispatched('lead.update.after');
    Event::assertDispatched('topweb_chat.lead.stage_changed');
});

it('refuses stage changes without leads.edit', function () {
    ['proposal' => $proposal, 'qualification' => $qualification, 'lead' => $lead, 'conversation' => $conversation] = stageContext();
    $limitedRole = Role::query()->create([
        'name' => 'Limitado', 'permission_type' => 'custom',
        'permissions' => [
            'topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view',
            'topweb_chat.inbox.stage', 'dashboard',
        ],
    ]);
    $limited = User::query()->create([
        'name' => 'Limitado', 'email' => 'limitado@example.com',
        'role_id' => $limitedRole->id, 'status' => true,
    ]);
    Lead::withoutEvents(
        fn () => $lead->forceFill(['user_id' => $limited->id])->save()
    );
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($limited, 'user');

    $this->put(
        route('admin.topweb_chat.lead_stage.update', $conversation),
        ['lead_pipeline_stage_id' => $proposal->id, '_token' => csrf_token()]
    )->assertForbidden();

    expect($lead->fresh()->lead_pipeline_stage_id)->toEqual($qualification->id);
});

it('refuses stage changes from a former owner', function () {
    ['agent' => $agent, 'proposal' => $proposal, 'qualification' => $qualification, 'lead' => $lead, 'conversation' => $conversation] = stageContext();
    $owner = User::query()->create([
        'name' => 'Dono', 'email' => 'dono@example.com',
        'role_id' => $agent->role_id, 'status' => true,
    ]);
    Lead::withoutEvents(
        fn () => $lead->forceFill(['user_id' => $owner->id])->save()
    );
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->put(
        route('admin.topweb_chat.lead_stage.update', $conversation),
        ['lead_pipeline_stage_id' => $proposal->id, '_token' => csrf_token()]
    )->assertForbidden();

    expect($lead->fresh()->lead_pipeline_stage_id)->toEqual($qualification->id);
});

it('moves the lead to a valid stage of another pipeline atomically', function () {
    ['agent' => $agent, 'qualification' => $qualification, 'lead' => $lead, 'conversation' => $conversation] = stageContext();
    Event::fake();
    $commercial = Pipeline::query()->create(['name' => 'Comercial']);
    $contact = Stage::query()->create([
        'code' => 'contact', 'name' => 'Contato',
        'probability' => 10, 'sort_order' => 1, 'lead_pipeline_id' => $commercial->id,
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->put(
        route('admin.topweb_chat.lead_stage.update', $conversation),
        [
            'lead_pipeline_id' => $commercial->id,
            'lead_pipeline_stage_id' => $contact->id,
            '_token' => csrf_token(),
        ]
    )->assertRedirect();

    $fresh = $lead->fresh();
    expect($fresh->lead_pipeline_id)->toEqual($commercial->id)
        ->and($fresh->lead_pipeline_stage_id)->toEqual($contact->id)
        ->and($fresh->user_id)->toEqual($agent->id);
    Event::assertDispatched('topweb_chat.lead.pipeline_changed');
});

it('answers stage updates as JSON for inline editing', function () {
    ['agent' => $agent, 'proposal' => $proposal, 'pipeline' => $pipeline, 'lead' => $lead, 'conversation' => $conversation] = stageContext();
    Event::fake();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $response = $this->putJson(
        route('admin.topweb_chat.lead_stage.update', $conversation),
        ['lead_pipeline_stage_id' => $proposal->id, '_token' => csrf_token()]
    )->assertOk();

    expect($response->json('lead_pipeline_id'))->toEqual($pipeline->id)
        ->and($response->json('lead_pipeline_stage_id'))->toEqual($proposal->id);
});

it('keeps stage changes working while the instance is not ready', function () {
    ['agent' => $agent, 'proposal' => $proposal, 'lead' => $lead, 'conversation' => $conversation] = stageContext();
    Event::fake();
    $conversation->instance->forceFill(['status' => 'disconnected'])->save();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->put(
        route('admin.topweb_chat.lead_stage.update', $conversation),
        ['lead_pipeline_stage_id' => $proposal->id, '_token' => csrf_token()]
    )->assertRedirect();

    expect($lead->fresh()->lead_pipeline_stage_id)->toEqual($proposal->id);
});

it('lists stages of a pipeline for authorized wallets only', function () {
    ['agent' => $agent, 'pipeline' => $pipeline, 'conversation' => $conversation] = stageContext();
    $this->actingAs($agent, 'user');

    $response = $this->getJson(
        route('admin.topweb_chat.lead_stage.stages', [$conversation, $pipeline])
    )->assertOk();

    expect($response->json('data'))->toEqual([
        ['id' => 1, 'name' => 'Qualificação'],
        ['id' => 2, 'name' => 'Proposta'],
    ]);

    $strangerRole = Role::query()->create([
        'name' => 'Estranho', 'permission_type' => 'custom',
        'permissions' => [
            'topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view',
            'topweb_chat.inbox.stage', 'leads.edit', 'dashboard',
        ],
    ]);
    $stranger = User::query()->create([
        'name' => 'Estranho', 'email' => 'estranho@example.com',
        'role_id' => $strangerRole->id, 'status' => true,
    ]);
    $this->actingAs($stranger, 'user');

    $this->getJson(
        route('admin.topweb_chat.lead_stage.stages', [$conversation, $pipeline])
    )->assertForbidden();
});

it('rejects stages from another pipeline and keeps the previous value', function () {
    ['agent' => $agent, 'qualification' => $qualification, 'lead' => $lead, 'conversation' => $conversation] = stageContext();
    $otherPipeline = Pipeline::query()->create(['name' => 'Comercial']);
    $alien = Stage::query()->create([
        'code' => 'alien', 'name' => 'Alheia',
        'probability' => 10, 'sort_order' => 1, 'lead_pipeline_id' => $otherPipeline->id,
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->put(
        route('admin.topweb_chat.lead_stage.update', $conversation),
        ['lead_pipeline_stage_id' => $alien->id, '_token' => csrf_token()]
    )->assertNotFound();

    expect($lead->fresh()->lead_pipeline_stage_id)->toEqual($qualification->id);
});

it('rolls back pipeline changes when a post-update event fails', function () {
    ['agent' => $agent, 'proposal' => $proposal, 'qualification' => $qualification, 'lead' => $lead, 'conversation' => $conversation] = stageContext();
    Event::listen('topweb_chat.lead.stage_changed', function () {
        throw new RuntimeException('simulated stage refresh failure');
    });
    $this->actingAs($agent, 'user');

    expect(fn () => app(LeadStageService::class)->move(
        $conversation,
        $agent,
        $proposal->id
    ))->toThrow(RuntimeException::class);

    expect($lead->fresh()->lead_pipeline_stage_id)->toEqual($qualification->id);
});
