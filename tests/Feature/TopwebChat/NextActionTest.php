<?php

// V-04: próxima ação via Activity com envelope seguro (sem strings livres sem concessão).

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Services\NextActionService;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    config()->set('app.debug', false);

    foreach (['lead_activities', 'topweb_chat_attendances', 'activities', 'leads', 'users', 'roles', 'attributes', 'core_config'] as $table) {
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
        $table->unsignedInteger('role_id')->nullable();
        $table->boolean('status')->default(true);
        $table->boolean('can_view_sensitive_data')->default(false);
        $table->timestamps();
    });

    Schema::create('leads', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('activities', function (Blueprint $table) {
        $table->increments('id');
        $table->string('type')->nullable();
        $table->string('title')->nullable();
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

    Schema::create('attributes', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->nullable();
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

function actionContext(): array
{
    $role = Role::query()->create(['name' => 'Agente', 'permission_type' => 'custom']);
    $agent = User::query()->create([
        'name' => 'Agente', 'email' => 'agente@example.com',
        'role_id' => $role->id, 'status' => true,
    ]);
    $lead = Lead::query()->create(['title' => 'Cobertura 45', 'user_id' => $agent->id]);

    $link = fn ($activity) => DB::table('lead_activities')->insert([
        'activity_id' => $activity->id, 'lead_id' => $lead->id,
    ]);

    $overdue = Activity::query()->create([
        'title' => 'Visita secreta', 'type' => 'visit', 'is_done' => false,
        'schedule_from' => now()->subDay()->setTime(15, 30), 'user_id' => $agent->id,
    ]);
    $link($overdue);
    $today = Activity::query()->create([
        'title' => 'Ligar agora', 'type' => 'call', 'is_done' => false,
        'schedule_from' => now()->addHour(), 'user_id' => $agent->id,
    ]);
    $link($today);
    $note = Activity::query()->create([
        'title' => 'Anotacao', 'type' => 'note', 'is_done' => false,
    ]);
    $link($note);
    $system = Activity::query()->create([
        'title' => 'Criado', 'type' => 'system', 'is_done' => false,
    ]);
    $link($system);
    $done = Activity::query()->create([
        'title' => 'Feita', 'type' => 'meeting', 'is_done' => true,
        'schedule_from' => now()->subDays(2),
    ]);
    $link($done);

    return compact('agent', 'lead', 'overdue', 'today', 'note', 'system', 'done');
}

it('orders overdue before today and excludes notes, system, done and attendance', function () {
    ['lead' => $lead, 'overdue' => $overdue, 'today' => $today] = actionContext();
    $service = app(NextActionService::class);

    $first = $service->nextForLead($lead->id);

    expect($first->id)->toEqual($overdue->id);

    $ids = $service->candidatesForLead($lead->id)->pluck('id')->all();
    expect($ids)->toEqual([$overdue->id, $today->id]);
});

it('serializes a safe envelope without free strings', function () {
    ['agent' => $agent, 'overdue' => $overdue] = actionContext();
    $service = app(NextActionService::class);

    $envelope = $service->envelope($overdue, $agent, false);

    expect($envelope['kind'])->toEqual('VISIT');
    expect($envelope['status'])->toEqual('overdue');
    expect($envelope['when'])->toContain('15:30');
    expect($envelope['owner'])->toEqual('Agente');
    expect(json_encode($envelope))->not->toContain('Visita secreta');
});

it('hides the owner from other non-admin viewers', function () {
    ['lead' => $lead, 'today' => $today] = actionContext();
    $other = User::query()->create(['name' => 'Outro', 'email' => 'outro@example.com', 'status' => true]);
    $service = app(NextActionService::class);

    expect($service->envelope($today, $other, false)['owner'])->toBeNull();
});

it('lists recent envelopes without system noise', function () {
    ['agent' => $agent, 'lead' => $lead, 'today' => $today] = actionContext();
    $service = app(NextActionService::class);

    $recents = $service->recentEnvelopes($lead->id, $agent, false);

    expect($recents)->not->toBeEmpty();
    expect(collect($recents)->pluck('kind')->all())->not->toContain('SYSTEM');
    expect(json_encode($recents))->not->toContain('Ligar agora');

    expect($service->recentEnvelopes(999999, $agent, false))->toEqual([]);
});

it('keeps instance technical and recents in the aside', function () {
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect($view)->toContain('next_action.recent', 'topweb_chat.settings.index');
});
