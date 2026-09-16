<?php

// E14-R2 S3: Activities inline pela fronteira do TopwebChat.

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Services\NextActionService;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['lead_activities', 'person_activities', 'activities', 'leads', 'persons', 'topweb_chat_attendances', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'core_config', 'attributes'] as $table) {
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

    Schema::create('leads', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('activities', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->string('type');
        $table->text('comment')->nullable();
        $table->string('location')->nullable();
        $table->json('additional')->nullable();
        $table->dateTime('schedule_from')->nullable();
        $table->dateTime('schedule_to')->nullable();
        $table->boolean('is_done')->default(false);
        $table->unsignedInteger('user_id');
        $table->timestamps();
    });

    Schema::create('lead_activities', function (Blueprint $table) {
        $table->unsignedInteger('lead_id');
        $table->unsignedInteger('activity_id');
    });

    Schema::create('person_activities', function (Blueprint $table) {
        $table->unsignedInteger('activity_id');
        $table->unsignedInteger('person_id');
    });

    Schema::create('topweb_chat_attendances', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('activity_id');
        $table->unsignedInteger('sequence');
        $table->timestamp('opened_at');
        $table->timestamp('last_real_message_at');
        $table->timestamp('closed_at')->nullable();
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

function activityContext(): array
{
    $adminRole = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $agentRole = Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => [
            'topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view',
            'topweb_chat.inbox.activities', 'activities.create', 'activities.edit', 'dashboard',
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
    $person = Person::withoutEvents(
        fn () => Person::query()->create(['name' => 'Zeta'])
    );
    $lead = Lead::withoutEvents(
        fn () => Lead::query()->create([
            'title' => 'Zeta Negocio', 'person_id' => $person->id, 'user_id' => $agent->id,
        ])
    );
    $instance = Instance::query()->create([
        'name' => 'E2E', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => false,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id, 'person_id' => $person->id, 'lead_id' => $lead->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);

    return compact('admin', 'agent', 'person', 'lead', 'conversation');
}

it('creates an actionable activity bound to the conversation lead', function () {
    ['agent' => $agent, 'lead' => $lead, 'conversation' => $conversation] = activityContext();
    Event::fake();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->post(
        route('admin.topweb_chat.activities.store', $conversation),
        [
            'type' => 'visit',
            'schedule_from' => now()->addDay()->format('Y-m-d H:i'),
            'schedule_to' => now()->addDay()->addHour()->format('Y-m-d H:i'),
            '_token' => csrf_token(),
        ]
    )->assertRedirect();

    $activity = Activity::query()->first();
    expect($activity->type)->toBe('visit')
        ->and($activity->user_id)->toEqual($agent->id)
        ->and((bool) $activity->is_done)->toBeFalse()
        ->and($activity->leads()->where('leads.id', $lead->id)->exists())->toBeTrue();
    Event::assertDispatched('activity.create.before');
    Event::assertDispatched('activity.create.after');
});

it('never elects record, system or unknown types as next action', function () {
    ['agent' => $agent, 'lead' => $lead] = activityContext();

    foreach (['note', 'system', 'mystery-type'] as $type) {
        $activity = Activity::query()->create([
            'title' => 'x', 'type' => $type, 'is_done' => false,
            'user_id' => $agent->id, 'schedule_from' => now()->addDay(),
        ]);
        $activity->leads()->syncWithoutDetaching([$lead->id]);
    }

    $service = app(NextActionService::class);

    expect($service->nextForLead($lead->id))->toBeNull()
        ->and($service->candidatesForLead($lead->id))->toBeEmpty();
});

it('promotes the next candidate after completion', function () {
    ['agent' => $agent, 'lead' => $lead, 'conversation' => $conversation] = activityContext();
    Event::fake();
    $first = Activity::query()->create([
        'title' => 'primeira', 'type' => 'call', 'is_done' => false,
        'user_id' => $agent->id, 'schedule_from' => now()->addDay(),
    ]);
    $first->leads()->syncWithoutDetaching([$lead->id]);
    $second = Activity::query()->create([
        'title' => 'segunda', 'type' => 'visit', 'is_done' => false,
        'user_id' => $agent->id, 'schedule_from' => now()->addDays(2),
    ]);
    $second->leads()->syncWithoutDetaching([$lead->id]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->post(
        route('admin.topweb_chat.activities.complete', [$conversation, $first]),
        ['_token' => csrf_token()]
    )->assertRedirect();

    expect((bool) $first->fresh()->is_done)->toBeTrue();

    $service = app(NextActionService::class);

    expect($service->nextForLead($lead->id)?->id)->toEqual($second->id);
    Event::assertDispatched('activity.update.before');
    Event::assertDispatched('activity.update.after');
});

it('reorders candidates after reschedule', function () {
    ['agent' => $agent, 'lead' => $lead, 'conversation' => $conversation] = activityContext();
    Event::fake();
    $soon = Activity::query()->create([
        'title' => 'cedo', 'type' => 'call', 'is_done' => false,
        'user_id' => $agent->id, 'schedule_from' => now()->addDay(),
        'schedule_to' => now()->addDay()->addHour(),
    ]);
    $soon->leads()->syncWithoutDetaching([$lead->id]);
    $later = Activity::query()->create([
        'title' => 'tarde', 'type' => 'visit', 'is_done' => false,
        'user_id' => $agent->id, 'schedule_from' => now()->addDays(2),
        'schedule_to' => now()->addDays(2)->addHour(),
    ]);
    $later->leads()->syncWithoutDetaching([$lead->id]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $service = app(NextActionService::class);
    expect($service->nextForLead($lead->id)?->id)->toEqual($soon->id);

    $this->put(
        route('admin.topweb_chat.activities.update', [$conversation, $soon]),
        [
            'schedule_from' => now()->addDays(5)->format('Y-m-d H:i'),
            'schedule_to' => now()->addDays(5)->addHour()->format('Y-m-d H:i'),
            '_token' => csrf_token(),
        ]
    )->assertRedirect();

    expect($service->nextForLead($lead->id)?->id)->toEqual($later->id);
});

it('rejects non-actionable types on creation', function () {
    ['agent' => $agent, 'conversation' => $conversation] = activityContext();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    foreach (['note', 'system', 'mystery-type'] as $type) {
        $this->post(
            route('admin.topweb_chat.activities.store', $conversation),
            [
                'type' => $type,
                'schedule_from' => now()->addDay()->format('Y-m-d H:i'),
                'schedule_to' => now()->addDay()->addHour()->format('Y-m-d H:i'),
                '_token' => csrf_token(),
            ]
        )->assertStatus(422);
    }

    expect(Activity::query()->count())->toBe(0);
});

it('refuses mutations on activities from another lead', function () {
    ['agent' => $agent, 'lead' => $lead, 'conversation' => $conversation] = activityContext();
    $alien = Activity::query()->create([
        'title' => 'alheia', 'type' => 'call', 'is_done' => false,
        'user_id' => $agent->id, 'schedule_from' => now()->addDay(),
        'schedule_to' => now()->addDay()->addHour(),
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->put(
        route('admin.topweb_chat.activities.update', [$conversation, $alien]),
        [
            'schedule_from' => now()->addDays(5)->format('Y-m-d H:i'),
            'schedule_to' => now()->addDays(5)->addHour()->format('Y-m-d H:i'),
            '_token' => csrf_token(),
        ]
    )->assertNotFound();

    $this->post(
        route('admin.topweb_chat.activities.complete', [$conversation, $alien]),
        ['_token' => csrf_token()]
    )->assertNotFound();

    expect((bool) $alien->fresh()->is_done)->toBeFalse();
});

it('rejects creation on conversations without a lead', function () {
    ['agent' => $agent, 'conversation' => $conversation] = activityContext();
    $conversation->forceFill(['lead_id' => null, 'assigned_user_id' => $agent->id])->save();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->post(
        route('admin.topweb_chat.activities.store', $conversation),
        [
            'type' => 'visit',
            'schedule_from' => now()->addDay()->format('Y-m-d H:i'),
            'schedule_to' => now()->addDay()->addHour()->format('Y-m-d H:i'),
            '_token' => csrf_token(),
        ]
    )->assertStatus(422);

    expect(Activity::query()->count())->toBe(0);
});

it('forces the current user as owner for non-admins', function () {
    ['admin' => $admin, 'agent' => $agent, 'conversation' => $conversation] = activityContext();
    Event::fake();
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->post(
        route('admin.topweb_chat.activities.store', $conversation),
        [
            'type' => 'call',
            'schedule_from' => now()->addDay()->format('Y-m-d H:i'),
            'schedule_to' => now()->addDay()->addHour()->format('Y-m-d H:i'),
            'user_id' => $admin->id,
            '_token' => csrf_token(),
        ]
    )->assertRedirect();

    expect(Activity::query()->first()->user_id)->toEqual($agent->id);
});

it('refuses to reschedule attendance-linked activities', function () {
    ['agent' => $agent, 'lead' => $lead, 'conversation' => $conversation] = activityContext();
    $attendance = Activity::query()->create([
        'title' => 'atendimento', 'type' => 'call', 'is_done' => false,
        'user_id' => $agent->id, 'schedule_from' => now()->subHour(),
    ]);
    $attendance->leads()->syncWithoutDetaching([$lead->id]);
    DB::table('topweb_chat_attendances')->insert([
        'conversation_id' => $conversation->id, 'activity_id' => $attendance->id,
        'sequence' => 1, 'opened_at' => now()->subHour(),
        'last_real_message_at' => now()->subHour(),
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($agent, 'user');

    $this->put(
        route('admin.topweb_chat.activities.update', [$conversation, $attendance]),
        [
            'schedule_from' => now()->addDays(5)->format('Y-m-d H:i'),
            'schedule_to' => now()->addDays(5)->addHour()->format('Y-m-d H:i'),
            '_token' => csrf_token(),
        ]
    )->assertStatus(422);
});

it('refuses creation on a lead outside the wallet', function () {
    ['conversation' => $conversation] = activityContext();
    $outsiderRole = Role::query()->create([
        'name' => 'Fora', 'permission_type' => 'custom',
        'permissions' => [
            'topweb_chat', 'topweb_chat.inbox', 'topweb_chat.inbox.view',
            'topweb_chat.inbox.activities', 'activities.create', 'dashboard',
        ],
    ]);
    $outsider = User::query()->create([
        'name' => 'Fora', 'email' => 'fora@example.com',
        'role_id' => $outsiderRole->id, 'status' => true,
    ]);
    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($outsider, 'user');

    $this->post(
        route('admin.topweb_chat.activities.store', $conversation),
        [
            'type' => 'visit',
            'schedule_from' => now()->addDay()->format('Y-m-d H:i'),
            'schedule_to' => now()->addDay()->addHour()->format('Y-m-d H:i'),
            '_token' => csrf_token(),
        ]
    )->assertForbidden();

    expect(Activity::query()->count())->toBe(0);
});
