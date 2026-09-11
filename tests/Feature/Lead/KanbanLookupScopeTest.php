<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['persons', 'lead_types', 'lead_sources', 'users', 'roles', 'core_config'] as $table) {
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
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('lead_types', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('lead_sources', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });
});

it('hides cross-portfolio persons from kanban lookup', function () {
    $roleId = DB::table('roles')->insertGetId([
        'name' => 'Corretor', 'permission_type' => 'custom',
        'permissions' => json_encode(['leads.view']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $mariaId = DB::table('users')->insertGetId([
        'name' => 'Maria', 'email' => 'maria@example.com',
        'status' => true, 'role_id' => $roleId, 'view_permission' => 'individual',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $joaoId = DB::table('users')->insertGetId([
        'name' => 'Joao', 'email' => 'joao@example.com',
        'status' => true, 'role_id' => $roleId, 'view_permission' => 'individual',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('persons')->insert([
        'name' => 'Leonardo', 'user_id' => $mariaId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // João must not discover Maria's Leonardo — not even existence.
    $this->actingAs(User::query()->findOrFail($joaoId), 'user')
        ->getJson(route('admin.leads.kanban.look_up', [
            'column' => 'person.id', 'search' => 'Leonardo',
        ]))
        ->assertOk()
        ->assertExactJson([]);

    // Maria finds her own Leonardo.
    $this->actingAs(User::query()->findOrFail($mariaId), 'user')
        ->getJson(route('admin.leads.kanban.look_up', [
            'column' => 'person.id', 'search' => 'Leonardo',
        ]))
        ->assertOk()
        ->assertJsonFragment(['label' => 'Leonardo']);
});
