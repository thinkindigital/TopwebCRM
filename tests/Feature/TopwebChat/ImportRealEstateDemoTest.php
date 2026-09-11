<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach (['leads', 'persons', 'products', 'lead_pipeline_stages', 'lead_stages', 'lead_pipelines', 'lead_sources', 'lead_types', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('lead_pipelines', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name')->unique();
        $table->boolean('is_default')->default(false);
        $table->integer('rotten_days')->default(30);
        $table->timestamps();
    });

    Schema::create('lead_stages', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('name');
        $table->boolean('is_user_defined')->default(true);
        $table->timestamps();
    });

    Schema::create('lead_pipeline_stages', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->nullable();
        $table->string('name')->nullable();
        $table->integer('probability')->default(0);
        $table->integer('sort_order')->default(0);
        $table->unsignedInteger('lead_pipeline_id');
    });

    Schema::create('lead_sources', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('lead_types', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table) {
        $table->increments('id');
        $table->string('sku')->unique();
        $table->string('name')->nullable();
        $table->string('description')->nullable();
        $table->integer('quantity')->default(0);
        $table->decimal('price', 12, 4)->nullable();
        $table->timestamps();
    });

    Schema::create('persons', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->json('emails');
        $table->json('contact_numbers')->nullable();
        $table->timestamps();
    });

    Schema::create('leads', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->text('description')->nullable();
        $table->decimal('lead_value', 12, 4)->nullable();
        $table->boolean('status')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('person_id');
        $table->unsignedInteger('lead_source_id');
        $table->unsignedInteger('lead_type_id')->nullable();
        $table->unsignedInteger('lead_pipeline_id')->nullable();
        $table->unsignedInteger('lead_stage_id')->nullable();
        $table->unsignedInteger('lead_pipeline_stage_id')->nullable();
        $table->date('expected_close_date')->nullable();
        $table->timestamps();
    });
});

it('imports real estate demo data from csv without deleting existing users', function () {
    DB::table('users')->insert([
        'name' => 'Admin Existente',
        'email' => 'admin@example.com',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $csv = storage_path('app/demo-import-test.csv');
    file_put_contents($csv, implode("\n", [
        'lead_title,person_name,person_email,phone,pipeline,stage_code,stage,source,type,value,expected_close_days,product_sku,product_name,product_description,lead_description',
        'Lead Demo,Ana Demo,ana.demo@example.com,11999999999,Pipeline Demo,novo,Novo Lead,WhatsApp,Venda,500000,15,APT-DEMO,Apartamento Demo,Imóvel completo,Lead quente',
    ]));

    $this->artisan('topwebchat:import-real-estate-demo', [
        'csv' => $csv,
        '--user-email' => 'admin@example.com',
    ])->assertSuccessful();

    $this->artisan('topwebchat:import-real-estate-demo', [
        'csv' => $csv,
        '--user-email' => 'admin@example.com',
    ])->assertSuccessful();

    expect(DB::table('users')->count())->toBe(1)
        ->and(DB::table('products')->where('sku', 'APT-DEMO')->count())->toBe(1)
        ->and(DB::table('persons')->where('emails', 'like', '%ana.demo@example.com%')->count())->toBe(1)
        ->and(DB::table('leads')->where('title', 'Lead Demo')->count())->toBe(1)
        ->and(DB::table('leads')->where('title', 'Lead Demo')->value('user_id'))->toBe(1);

    @unlink($csv);
});
