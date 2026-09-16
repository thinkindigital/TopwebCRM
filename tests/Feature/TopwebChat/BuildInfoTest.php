<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    foreach (['users', 'roles', 'core_config'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value');
        $table->timestamps();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('description')->nullable();
        $table->string('permission_type');
        $table->json('permissions')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email');
        $table->string('password');
        $table->unsignedInteger('role_id')->nullable();
        $table->boolean('status')->default(true);
        $table->string('view_permission')->default('individual');
        $table->boolean('can_view_sensitive_data')->default(false);
        $table->timestamps();
    });
});

function buildInfoUser(string $name, string $permissionType): User
{
    $role = Role::query()->create([
        'name' => $name.' role',
        'permission_type' => $permissionType,
        'permissions' => ['topweb_chat.settings'],
    ]);

    return User::query()->create([
        'name' => $name,
        'email' => strtolower($name).'@example.test',
        'password' => Hash::make('password'),
        'role_id' => $role->id,
        'status' => true,
    ]);
}

it('returns only reproducible build metadata to administrators', function () {
    $admin = buildInfoUser('Admin', 'all');
    config()->set('build', [
        'commit_sha' => 'abc123',
        'timestamp' => '2026-09-16T00:00:00Z',
        'branch' => 'dev',
        'image_tag' => 'dev',
        'image_revision' => 'sha-abc123',
    ]);
    $this->actingAs($admin, 'user');

    $this->getJson(route('admin.topweb_chat.settings.build_info'))
        ->assertOk()
        ->assertJson([
            'application_commit_sha' => 'abc123',
            'branch' => 'dev',
            'image_revision' => 'sha-abc123',
        ])
        ->assertJsonMissing(['password', 'token', 'secret']);
});

it('blocks build metadata for non-administrators', function () {
    $user = buildInfoUser('Agent', 'custom');
    $this->actingAs($user, 'user');

    $this->getJson(route('admin.topweb_chat.settings.build_info'))->assertForbidden();
});
