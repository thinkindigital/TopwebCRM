<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach (['topweb_chat_conversations', 'topweb_chat_instances', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('topweb_chat_instances', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('provider')->default('openwa');
        $table->string('session_uuid')->nullable();
        $table->string('status')->nullable();
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('topweb_chat_conversations', function (Blueprint $table) {
        $table->increments('id');
        $table->timestamps();
    });
});

it('fails cleanly for an unknown chat instance', function () {
    $this->artisan('topwebchat:smoke', [
        '--instance' => 999,
        '--to' => '5511993193118',
    ])
        ->expectsOutputToContain('Instance 999 not found')
        ->assertFailed();
});

it('resolves instance, owner and target in dry-run without queueing', function () {
    DB::table('users')->insert([
        'name' => 'Admin Existente',
        'email' => 'admin@example.com',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('topweb_chat_instances')->insert([
        'name' => 'Suporte',
        'provider' => 'openwa',
        'status' => 'ready',
        'enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('topwebchat:smoke', [
        '--instance' => 1,
        '--to' => '5511993193118',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('owner: admin@example.com')
        ->expectsOutputToContain('target: 5511993193118')
        ->assertSuccessful();

    expect(DB::table('topweb_chat_conversations')->count())->toBe(0);
});
