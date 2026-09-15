<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\LeadDistributionPool;
use Webkul\Lead\Models\LeadDistributionPoolUser;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['lead_distribution_decisions', 'lead_distribution_states', 'topweb_chat_conversations', 'lead_activities', 'activities', 'attributes', 'leads', 'lead_pipeline_stages', 'lead_pipelines', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

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

    Schema::create('leads', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('lead_pipeline_id')->nullable();
        $table->unsignedInteger('lead_pipeline_stage_id')->nullable();
        $table->timestamps();
    });

    Schema::create('activities', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->string('type');
        $table->text('comment')->nullable();
        $table->json('additional')->nullable();
        $table->boolean('is_done')->default(false);
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('attributes', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->nullable();
        $table->string('name')->nullable();
        $table->string('type')->nullable();
        $table->string('entity_type')->nullable();
        $table->timestamps();
    });

    Schema::create('lead_activities', function (Blueprint $table) {
        $table->unsignedInteger('activity_id');
        $table->unsignedInteger('lead_id');
    });

    Schema::create('topweb_chat_conversations', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('lead_id')->nullable();
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->string('remote_jid')->nullable();
        $table->char('remote_jid_key', 64)->nullable();
        $table->string('status')->default('open');
        $table->timestamps();
    });

    Schema::create('lead_distribution_states', function (Blueprint $table) {
        $table->id();
        $table->string('pool_key')->unique();
        $table->unsignedInteger('last_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('lead_distribution_decisions', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('lead_id');
        $table->unsignedInteger('selected_user_id')->nullable();
        $table->string('strategy');
        $table->string('pool_key');
        $table->json('candidate_user_ids');
        $table->string('result');
        $table->string('reason')->nullable();
        $table->timestamps();
    });

    Schema::create('lead_distribution_pools', function (Blueprint $table) {
        $table->id();
        $table->string('pool_key')->unique();
        $table->string('strategy');
        $table->unsignedInteger('fallback_user_id')->nullable();
        $table->json('constraints')->nullable();
        $table->timestamps();
    });

    Schema::create('lead_distribution_pool_users', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('pool_id');
        $table->unsignedInteger('user_id');
        $table->boolean('enabled')->default(false);
        $table->string('region')->nullable();
        $table->decimal('score', 10, 3)->default(0);
        $table->timestamps();
        $table->unique(['pool_id', 'user_id']);
    });
});

function distributionContext(): array
{
    $first = User::query()->create(['name' => 'Primeiro', 'status' => true]);
    $second = User::query()->create(['name' => 'Segundo', 'status' => true]);
    $pipelineId = DB::table('lead_pipelines')->insertGetId([
        'name' => 'Distribuicao', 'rotten_days' => 0, 'is_default' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $stageId = DB::table('lead_pipeline_stages')->insertGetId([
        'code' => 'new', 'name' => 'Novo', 'probability' => 0, 'sort_order' => 0,
        'lead_pipeline_id' => $pipelineId,
    ]);
    $leadId = DB::table('leads')->insertGetId([
        'title' => 'Lead distribuivel', 'lead_pipeline_id' => $pipelineId,
        'lead_pipeline_stage_id' => $stageId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $conversationId = DB::table('topweb_chat_conversations')->insertGetId([
        'lead_id' => $leadId, 'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('first', 'second', 'leadId', 'conversationId');
}

it('distributes a lead round-robin and keeps its conversation aligned', function () {
    ['first' => $first, 'second' => $second, 'leadId' => $leadId, 'conversationId' => $conversationId] = distributionContext();

    $selected = app(\Webkul\Lead\Services\LeadDistributionService::class)->assignRoundRobin(
        Lead::query()->findOrFail($leadId),
        [$first->id, $second->id],
        $conversationId,
    );

    expect($selected->id)->toBe($first->id)
        ->and(Lead::query()->findOrFail($leadId)->user_id)->toBe($first->id)
        ->and(DB::table('topweb_chat_conversations')->where('id', $conversationId)->value('assigned_user_id'))->toBe($first->id)
        ->and(DB::table('lead_distribution_decisions')->where('lead_id', $leadId)->value('strategy'))->toBe('round_robin');
});

it('rotates the next lead after the previous assignment', function () {
    ['first' => $first, 'second' => $second, 'leadId' => $leadId] = distributionContext();
    $service = app(\Webkul\Lead\Services\LeadDistributionService::class);

    $service->assignRoundRobin(Lead::query()->findOrFail($leadId), [$first->id, $second->id]);
    $nextLeadId = DB::table('leads')->insertGetId([
        'title' => 'Segundo lead', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $selected = $service->assignRoundRobin(Lead::query()->findOrFail($nextLeadId), [$first->id, $second->id]);

    expect($selected->id)->toBe($second->id)
        ->and(DB::table('lead_distribution_decisions')->count())->toBe(2);
});

it('excludes inactive candidates from the distribution pool', function () {
    ['first' => $first, 'second' => $second, 'leadId' => $leadId] = distributionContext();
    $second->update(['status' => false]);

    $selected = app(\Webkul\Lead\Services\LeadDistributionService::class)->assignRoundRobin(
        Lead::query()->findOrFail($leadId),
        [$first->id, $second->id],
    );

    expect($selected->id)->toBe($first->id)
        ->and(DB::table('lead_distribution_decisions')->value('candidate_user_ids'))->toContain((string) $first->id);
});

it('uses an active fallback when the eligible pool is empty', function () {
    ['first' => $first, 'second' => $second, 'leadId' => $leadId] = distributionContext();
    $first->update(['status' => false]);
    $second->update(['status' => false]);
    $fallback = User::query()->create(['name' => 'Fallback', 'status' => true]);

    $selected = app(\Webkul\Lead\Services\LeadDistributionService::class)->assignRoundRobin(
        Lead::query()->findOrFail($leadId),
        [$first->id, $second->id],
        null,
        'default',
        $fallback->id,
    );

    expect($selected->id)->toBe($fallback->id)
        ->and(DB::table('lead_distribution_decisions')->value('result'))->toBe('fallback');
});

it('initializes a custom pool exactly once before selecting the next user', function () {
    ['first' => $first, 'second' => $second, 'leadId' => $leadId] = distributionContext();

    $selected = app(\Webkul\Lead\Services\LeadDistributionService::class)->assignRoundRobin(
        Lead::query()->findOrFail($leadId),
        [$first->id, $second->id],
        null,
        'custom-pool',
    );

    expect($selected->id)->toBe($first->id)
        ->and(DB::table('lead_distribution_states')->where('pool_key', 'custom-pool')->count())->toBe(1);
});

it('excludes active users that are unavailable from the distribution pool', function () {
    ['first' => $first, 'second' => $second, 'leadId' => $leadId] = distributionContext();

    $selected = app(\Webkul\Lead\Services\LeadDistributionService::class)->assignRoundRobin(
        Lead::query()->findOrFail($leadId),
        [$first->id, $second->id],
        null,
        'availability-pool',
        null,
        [$second->id],
    );

    expect($selected->id)->toBe($second->id)
        ->and(DB::table('lead_distribution_decisions')->value('candidate_user_ids'))->toContain((string) $second->id)
        ->and(DB::table('lead_distribution_decisions')->value('candidate_user_ids'))->not->toContain((string) $first->id);
});

it('selects the highest scored candidate and audits the score-based rule', function () {
    ['first' => $first, 'second' => $second, 'leadId' => $leadId] = distributionContext();

    $selected = app(\Webkul\Lead\Services\LeadDistributionService::class)->assignRoundRobin(
        Lead::query()->findOrFail($leadId),
        [
            ['user_id' => $first->id, 'score' => 10, 'region' => 'sul'],
            ['user_id' => $second->id, 'score' => 20, 'region' => 'sul'],
        ],
    );

    expect($selected->id)->toBe($second->id)
        ->and(DB::table('lead_distribution_decisions')->value('strategy'))->toBe('score_round_robin');
});

it('uses round-robin to break equal score candidates', function () {
    ['first' => $first, 'second' => $second, 'leadId' => $leadId] = distributionContext();
    $service = app(\Webkul\Lead\Services\LeadDistributionService::class);
    $candidates = [
        ['user_id' => $first->id, 'score' => 20, 'region' => 'sul'],
        ['user_id' => $second->id, 'score' => 20, 'region' => 'sul'],
    ];

    $firstSelected = $service->assignRoundRobin(Lead::query()->findOrFail($leadId), $candidates, null, 'score-tie-pool');
    $secondLeadId = DB::table('leads')->insertGetId([
        'title' => 'Lead de desempate', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $secondSelected = $service->assignRoundRobin(Lead::query()->findOrFail($secondLeadId), $candidates, null, 'score-tie-pool');

    expect($firstSelected->id)->toBe($first->id)
        ->and($secondSelected->id)->toBe($second->id);
});

it('resolves a persisted pool before assigning a lead', function () {
    ['first' => $first, 'second' => $second, 'leadId' => $leadId] = distributionContext();
    $pool = LeadDistributionPool::query()->create([
        'pool_key' => 'persisted-pool',
        'strategy' => 'score_round_robin',
        'constraints' => ['regions' => ['sul']],
    ]);
    LeadDistributionPoolUser::query()->create([
        'pool_id' => $pool->id, 'user_id' => $first->id, 'enabled' => true,
        'region' => 'sul', 'score' => 10,
    ]);
    LeadDistributionPoolUser::query()->create([
        'pool_id' => $pool->id, 'user_id' => $second->id, 'enabled' => false,
        'region' => 'sul', 'score' => 20,
    ]);

    $selected = app(\Webkul\Lead\Services\LeadDistributionService::class)->assign(
        Lead::query()->findOrFail($leadId),
        'persisted-pool',
        ['region' => 'sul'],
    );

    expect($selected->id)->toBe($first->id)
        ->and(DB::table('lead_distribution_decisions')->value('strategy'))->toBe('score_round_robin')
        ->and(DB::table('lead_distribution_decisions')->value('reason'))->toBe('configured_pool_members');
});

it('uses the configured fallback when pool constraints have no available member', function () {
    ['first' => $first, 'leadId' => $leadId] = distributionContext();
    $pool = LeadDistributionPool::query()->create([
        'pool_key' => 'constrained-pool',
        'strategy' => 'score_round_robin',
        'fallback_user_id' => $first->id,
        'constraints' => ['regions' => ['sul']],
    ]);
    LeadDistributionPoolUser::query()->create([
        'pool_id' => $pool->id, 'user_id' => $first->id, 'enabled' => true,
        'region' => 'sul', 'score' => 10,
    ]);

    $selected = app(\Webkul\Lead\Services\LeadDistributionService::class)->assign(
        Lead::query()->findOrFail($leadId),
        'constrained-pool',
        ['region' => 'norte'],
    );

    expect($selected->id)->toBe($first->id)
        ->and(DB::table('lead_distribution_decisions')->value('result'))->toBe('fallback')
        ->and(DB::table('lead_distribution_decisions')->value('reason'))->toBe('pool_region_constraint_mismatch');
});
