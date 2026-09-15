<?php

// #19 (E10): ingestão idempotente de Leads por integrações externas.
// Tracer bullet: criar via API autenticada; repetir não duplica.
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    config()->set('app.debug', false);

    foreach (['lead_ingestions', 'persons', 'person_activities', 'leads', 'lead_sources', 'lead_pipelines', 'lead_pipeline_stages', 'activities', 'lead_activities', 'attributes', 'attribute_values', 'users', 'roles', 'personal_access_tokens', 'core_config'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('roles', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('permission_type');
        $table->json('permissions')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->boolean('status')->default(true);
        $table->unsignedInteger('role_id')->nullable();
        $table->string('view_permission')->nullable();
        $table->boolean('can_view_sensitive_data')->default(false);
        $table->timestamps();
    });

    Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->morphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('persons', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->json('emails')->nullable();
        $table->json('contact_numbers')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->string('unique_id')->nullable()->unique();
        $table->timestamps();
    });

    Schema::create('lead_sources', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
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
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('lead_pipeline_id')->nullable();
        $table->unsignedInteger('lead_pipeline_stage_id')->nullable();
        $table->unsignedInteger('lead_source_id')->nullable();
        $table->timestamps();
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

    Schema::create('attribute_values', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('attribute_id')->nullable();
        $table->unsignedInteger('entity_id')->nullable();
        $table->string('entity_type')->nullable();
        $table->text('text_value')->nullable();
        $table->timestamps();
    });

    Schema::create('lead_ingestions', function (Blueprint $table) {
        $table->id();
        $table->string('source');
        $table->string('source_lead_id')->nullable();
        $table->string('idempotency_key')->unique();
        $table->unsignedInteger('lead_id');
        $table->unsignedInteger('person_id')->nullable();
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

function ingestionContext(): array
{
    $role = Role::query()->create(['name' => 'Integracao', 'permission_type' => 'custom', 'permissions' => []]);
    $user = User::query()->create([
        'name' => 'Integracao', 'email' => 'integracao@example.com',
        'role_id' => $role->id, 'status' => true,
    ]);
    $owner = User::query()->create([
        'name' => 'Dono', 'email' => 'dono@example.com',
        'role_id' => $role->id, 'status' => true,
    ]);
    $sourceId = DB::table('lead_sources')->insertGetId(['name' => 'Meta Ads']);

    return compact('user', 'owner', 'sourceId');
}

function ingestionPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'meta',
        'source_lead_id' => 'meta-0001',
        'idempotency_key' => 'n8n-exec-0001',
        'owner_id' => null,
        'person' => ['name' => 'Cliente Externo', 'emails' => [['value' => 'externo@example.com']]],
        'lead' => ['title' => 'Negocio Externo'],
    ], $overrides);
}

it('ingests a lead idempotently through an authenticated integration', function () {
    ['user' => $user, 'owner' => $owner] = ingestionContext();
    Sanctum::actingAs($user, ['leads:ingest']);

    $payload = ingestionPayload(['owner_id' => $owner->id]);

    $first = $this->postJson('/api/v1/leads/ingest', $payload)->assertCreated();
    $leadId = $first->json('lead_id');
    $personId = $first->json('person_id');

    expect($leadId)->toBeInt()->and($personId)->toBeInt();
    expect($first->json('duplicate'))->toBeFalse();

    // Repetição: mesmos IDs, sem duplicar Pessoa, Lead ou ingestão.
    $second = $this->postJson('/api/v1/leads/ingest', $payload)->assertOk();

    expect($second->json('lead_id'))->toBe($leadId)
        ->and($second->json('person_id'))->toBe($personId)
        ->and($second->json('duplicate'))->toBeTrue();
    expect(DB::table('leads')->count())->toBe(1)
        ->and(DB::table('persons')->count())->toBe(1)
        ->and(DB::table('lead_ingestions')->count())->toBe(1);
});
