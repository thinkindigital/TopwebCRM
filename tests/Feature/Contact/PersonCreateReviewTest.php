<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['persons', 'users', 'roles', 'attributes', 'core_config', 'activities', 'person_activities', 'workflows'] as $table) {
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

    Schema::create('persons', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->text('emails')->nullable();
        $table->text('contact_numbers')->nullable();
        $table->string('job_title')->nullable();
        $table->unsignedInteger('organization_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->string('unique_id')->nullable();
        $table->timestamps();
    });

    Schema::create('attributes', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->nullable();
        $table->string('entity_type')->nullable();
        $table->timestamps();
    });

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });

    Schema::create('activities', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->string('type')->nullable();
        $table->text('comment')->nullable();
        $table->boolean('is_done')->default(false);
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('person_activities', function (Blueprint $table) {
        $table->unsignedInteger('activity_id')->nullable();
        $table->unsignedInteger('person_id')->nullable();
    });

    Schema::create('workflows', function (Blueprint $table) {
        $table->increments('id');
        $table->string('event')->nullable();
        $table->timestamps();
    });
});

function personCreateActor(array $overrides = []): User
{
    $roleId = DB::table('roles')->insertGetId([
        'name' => 'Corretor', 'permission_type' => 'custom',
        'permissions' => json_encode(['contacts.persons.create', 'contacts.persons.create.quick-create', 'contacts.persons.view']),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return User::query()->findOrFail(DB::table('users')->insertGetId(array_merge([
        'name' => 'Ator', 'email' => 'ator-'.uniqid().'@example.com',
        'status' => true, 'role_id' => $roleId, 'view_permission' => 'individual',
        'can_view_sensitive_data' => false,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides)));
}

it('sends duplicate phones to review without creating or leaking', function () {
    $maria = personCreateActor(['name' => 'Maria', 'email' => 'maria@example.com']);
    DB::table('persons')->insert([
        'name' => 'Leonardo', 'user_id' => $maria->id,
        'contact_numbers' => json_encode([['label' => 'mobile', 'value' => '+5511993193118']]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $joao = personCreateActor(['name' => 'Joao', 'email' => 'joao@example.com']);

    $this->actingAs($joao, 'user')
        ->post(route('admin.contacts.persons.store'), [
            'entity_type' => 'persons',
            'name' => 'Leonardo Novo',
            'contact_numbers' => [['label' => 'mobile', 'value' => '5511993193118']],
        ])
        ->assertRedirect(route('admin.contacts.persons.index'))
        ->assertSessionHas('success', trans('admin::app.contacts.persons.index.pending-review'));

    // No record created in anyone's portfolio — and nothing learned.
    expect(DB::table('persons')->count())->toBe(1)
        ->and(DB::table('persons')->where('user_id', $joao->id)->count())->toBe(0);
});

it('holds genuinely new phones for review instead of assigning', function () {
    $joao = personCreateActor(['name' => 'Joao', 'email' => 'joao@example.com']);

    $this->actingAs($joao, 'user')
        ->post(route('admin.contacts.persons.store'), [
            'entity_type' => 'persons',
            'name' => 'Contato Novo',
            'contact_numbers' => [['label' => 'mobile', 'value' => '5511888888888']],
        ])
        ->assertRedirect(route('admin.contacts.persons.index'))
        ->assertSessionHas('success', trans('admin::app.contacts.persons.index.pending-review'));

    $pending = DB::table('persons')->where('name', 'Contato Novo')->first();

    // Same neutral message as the duplicate case (indistinguishable).
    expect($pending)->not->toBeNull()
        ->and($pending->user_id)->toBeNull();
});

it('warns grant holders about duplicates without creating', function () {
    $maria = personCreateActor(['name' => 'Maria', 'email' => 'maria@example.com']);
    DB::table('persons')->insert([
        'name' => 'Leonardo', 'user_id' => $maria->id,
        'contact_numbers' => json_encode([['label' => 'mobile', 'value' => '+5511993193118']]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $admin = personCreateActor([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'can_view_sensitive_data' => true,
    ]);

    $this->actingAs($admin, 'user')
        ->post(route('admin.contacts.persons.store'), [
            'entity_type' => 'persons',
            'name' => 'Leonardo Copia',
            'contact_numbers' => [['label' => 'mobile', 'value' => '5511993193118']],
        ])
        ->assertRedirect(route('admin.contacts.persons.index'))
        ->assertSessionHas('warning');

    expect(DB::table('persons')->count())->toBe(1);
});
