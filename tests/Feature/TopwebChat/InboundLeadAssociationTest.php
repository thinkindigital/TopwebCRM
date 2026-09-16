<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\TopwebChat\Services\InboundLeadAssociationService;

beforeEach(function () {
    config()->set('topweb-chat.inbound.auto_create_lead', true);

    foreach (['organizations', 'persons', 'leads', 'lead_pipelines', 'lead_pipeline_stages'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('organizations', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('persons', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->json('emails')->nullable();
        $table->json('contact_numbers')->nullable();
        $table->unsignedInteger('organization_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->string('unique_id')->nullable();
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
        $table->boolean('status')->nullable();
        $table->date('expected_close_date')->nullable();
        $table->dateTime('closed_at')->nullable();
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('lead_pipeline_id')->nullable();
        $table->unsignedInteger('lead_pipeline_stage_id')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Mockery::close();
});

function inboundPerson(string $name = 'Cliente inbound'): Person
{
    $id = DB::table('persons')->insertGetId([
        'name' => $name,
        'emails' => json_encode([['value' => strtolower(str_replace(' ', '.', $name)).'@example.com']]),
        'contact_numbers' => json_encode([['value' => '+5511999999999']]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Person::query()->findOrFail($id);
}

function inboundPipeline(): Pipeline
{
    $pipeline = Pipeline::query()->create([
        'name' => 'Inbound', 'rotten_days' => 0, 'is_default' => true,
    ]);
    DB::table('lead_pipeline_stages')->insert([
        'code' => 'new', 'name' => 'Novo', 'probability' => 0,
        'sort_order' => 1, 'lead_pipeline_id' => $pipeline->id,
    ]);

    return $pipeline->fresh();
}

function inboundLead(int $personId, int $stageId, int $pipelineId, string $code = 'open'): Lead
{
    if ($code !== 'new') {
        DB::table('lead_pipeline_stages')->where('id', $stageId)->update(['code' => $code]);
    }

    $id = DB::table('leads')->insertGetId([
        'title' => 'Lead existente',
        'status' => true,
        'person_id' => $personId,
        'lead_pipeline_id' => $pipelineId,
        'lead_pipeline_stage_id' => $stageId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Lead::query()->findOrFail($id);
}

function inboundAssociationService($leads, $pipelines): InboundLeadAssociationService
{
    return new InboundLeadAssociationService($leads, $pipelines);
}

it('reuses the only operational lead for a person', function () {
    $pipeline = inboundPipeline();
    $person = inboundPerson();
    $existing = inboundLead($person->id, $pipeline->stages()->first()->id, $pipeline->id);

    $leads = Mockery::mock(LeadRepository::class);
    $pipelines = Mockery::mock(PipelineRepository::class);
    $pipelines->shouldReceive('getDefaultPipeline')->never();

    $result = inboundAssociationService($leads, $pipelines)->associate($person);

    expect($result?->is($existing))->toBeTrue();
});

it('creates one ownerless lead when no operational lead exists', function () {
    $pipeline = inboundPipeline();
    $person = inboundPerson();
    $created = new Lead(['id' => 99, 'person_id' => $person->id, 'user_id' => null]);
    $created->exists = true;

    $leads = Mockery::mock(LeadRepository::class);
    $leads->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data) => $data['person_id'] === $person->id
            && $data['user_id'] === null
            && $data['lead_pipeline_id'] === $pipeline->id
            && $data['lead_pipeline_stage_id'] === $pipeline->stages()->first()->id))
        ->andReturn($created);
    $pipelines = Mockery::mock(PipelineRepository::class);
    $pipelines->shouldReceive('getDefaultPipeline')->once()->andReturn($pipeline);

    $result = inboundAssociationService($leads, $pipelines)->associate($person);

    expect($result)->toBe($created);
});

it('does not choose between multiple operational leads', function () {
    $pipeline = inboundPipeline();
    $person = inboundPerson();
    inboundLead($person->id, $pipeline->stages()->first()->id, $pipeline->id);
    inboundLead($person->id, $pipeline->stages()->first()->id, $pipeline->id);

    $leads = Mockery::mock(LeadRepository::class);
    $leads->shouldReceive('create')->never();
    $pipelines = Mockery::mock(PipelineRepository::class);
    $pipelines->shouldReceive('getDefaultPipeline')->never();

    expect(inboundAssociationService($leads, $pipelines)->associate($person))->toBeNull();
});

it('ignores terminal leads before creating a new operational lead', function () {
    $pipeline = inboundPipeline();
    $person = inboundPerson();
    inboundLead($person->id, $pipeline->stages()->first()->id, $pipeline->id, 'lost');
    $created = new Lead(['id' => 100, 'person_id' => $person->id, 'user_id' => null]);
    $created->exists = true;

    $leads = Mockery::mock(LeadRepository::class);
    $leads->shouldReceive('create')->once()->andReturn($created);
    $pipelines = Mockery::mock(PipelineRepository::class);
    $pipelines->shouldReceive('getDefaultPipeline')->once()->andReturn($pipeline);

    expect(inboundAssociationService($leads, $pipelines)->associate($person))->toBe($created);
});
