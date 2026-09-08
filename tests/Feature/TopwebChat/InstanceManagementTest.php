<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['topweb_chat_instances', 'users', 'roles', 'attributes', 'core_config'] as $table) {
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

    Schema::create('attributes', function (Blueprint $table) {
        $table->id();
        $table->string('entity_type');
        $table->boolean('quick_add')->default(false);
    });

    Schema::create('topweb_chat_instances', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
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
        $table->foreignId('instance_id')
            ->constrained('topweb_chat_instances')
            ->cascadeOnDelete();
    });

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });

    Queue::fake();
    touch(storage_path('installed'));
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

function instanceAdminContext(): string
{
    $role = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $user = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'role_id' => $role->id, 'status' => true,
    ]);
    test()->withSession(['_token' => 'csrf-test-token']);
    test()->actingAs($user, 'user');

    return csrf_token();
}

function instancePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Suporte 1',
        'session_uuid' => (string) Str::uuid(),
        'base_url' => 'https://openwa.exemplo.com.br',
        'token' => 'test-token',
    ], $overrides);
}

function instanceSettingsProvider(): void
{
    $provider = Mockery::mock(MessagingProvider::class);
    $provider->shouldReceive('health')->andReturn(['status' => 'ok']);
    $provider->shouldReceive('listSessions')->andReturn([]);

    app()->instance(MessagingProvider::class, $provider);
}

it('rejects duplicate instance names with validation instead of 500', function () {
    $csrfToken = instanceAdminContext();
    instanceSettingsProvider();

    Instance::query()->create([
        'name' => 'Suporte 1', 'provider' => 'openwa',
        'session_uuid' => (string) Str::uuid(),
    ]);

    $this->post(route('admin.topweb_chat.settings.instances.store'), instancePayload([
        '_token' => $csrfToken,
    ]))
        ->assertStatus(302)
        ->assertSessionHasErrors([
            'name' => trans('topweb_chat::app.settings.instance_name_taken'),
        ]);

    expect(Instance::query()->count())->toBe(1);

    $this->get(route('admin.topweb_chat.settings.index'))
        ->assertOk()
        ->assertSee('data-testid="instance-name-error"', false)
        ->assertSee(trans('topweb_chat::app.settings.instance_name_taken'));
});

it('escapes the instance name in the deletion confirmation', function () {
    instanceAdminContext();
    instanceSettingsProvider();

    Instance::query()->create([
        'name' => '<img src=x onerror=alert(1)>',
        'provider' => 'openwa',
        'session_uuid' => (string) Str::uuid(),
    ]);

    $this->get(route('admin.topweb_chat.settings.index'))
        ->assertOk()
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
});

it('rejects a non scalar session uuid without persisting', function () {
    $csrfToken = instanceAdminContext();

    $this->post(route('admin.topweb_chat.settings.instances.store'), instancePayload([
        '_token' => $csrfToken,
        'session_uuid' => ['invalid'],
    ]))
        ->assertStatus(302)
        ->assertSessionHasErrors('session_uuid');

    expect(Instance::query()->count())->toBe(0);
});

it('deletes an instance only with exact name confirmation', function () {
    $csrfToken = instanceAdminContext();

    $instance = Instance::query()->create([
        'name' => 'Antiga', 'provider' => 'openwa',
        'session_uuid' => (string) Str::uuid(),
    ]);

    $this->delete(
        route('admin.topweb_chat.settings.instances.destroy', $instance),
        ['_token' => $csrfToken, 'confirmation_name' => 'Errada']
    )
        ->assertStatus(302)
        ->assertSessionHas('error', trans('topweb_chat::app.settings.instance_delete_mismatch'));
    expect(Instance::query()->whereKey($instance->id)->exists())->toBeTrue();

    DB::table('topweb_chat_conversations')->insert([
        'instance_id' => $instance->id,
    ]);

    $this->delete(
        route('admin.topweb_chat.settings.instances.destroy', $instance),
        ['_token' => $csrfToken, 'confirmation_name' => 'Antiga']
    )
        ->assertStatus(302)
        ->assertSessionHas('success', trans('topweb_chat::app.settings.instance_deleted', [
            'count' => 1,
        ]));

    expect(Instance::query()->whereKey($instance->id)->exists())->toBeFalse()
        ->and(DB::table('topweb_chat_conversations')->count())->toBe(0);
});

it('forbids non administrators from deleting an instance', function () {
    $role = Role::query()->create([
        'name' => 'Agente',
        'permission_type' => 'custom',
        'permissions' => ['topweb_chat.settings'],
    ]);
    $user = User::query()->create([
        'name' => 'Agente',
        'email' => 'agente@example.com',
        'role_id' => $role->id,
        'status' => true,
    ]);
    $instance = Instance::query()->create([
        'name' => 'Protegida',
        'provider' => 'openwa',
        'session_uuid' => (string) Str::uuid(),
    ]);

    $this->withSession(['_token' => 'csrf-test-token']);
    $this->actingAs($user, 'user');

    $this->delete(route('admin.topweb_chat.settings.instances.destroy', $instance), [
        '_token' => csrf_token(),
        'confirmation_name' => 'Protegida',
    ])->assertForbidden();

    expect(Instance::query()->whereKey($instance->id)->exists())->toBeTrue();
});

it('updates the existing instance when the session uuid is unchanged', function () {
    $csrfToken = instanceAdminContext();

    $sessionUuid = (string) Str::uuid();
    $instance = Instance::query()->create([
        'name' => 'Suporte 1',
        'provider' => 'openwa',
        'session_uuid' => $sessionUuid,
        'base_url' => 'https://openwa-antigo.exemplo.com.br',
    ]);

    $this->post(route('admin.topweb_chat.settings.instances.store'), instancePayload([
        '_token' => $csrfToken,
        'session_uuid' => $sessionUuid,
        'base_url' => 'https://openwa-novo.exemplo.com.br',
    ]))->assertStatus(302)->assertSessionHasNoErrors();

    expect(Instance::query()->count())->toBe(1)
        ->and($instance->fresh()->base_url)->toBe('https://openwa-novo.exemplo.com.br');
});
