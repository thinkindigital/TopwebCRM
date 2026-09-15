<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_distribution_states', function (Blueprint $table) {
            $table->id();
            $table->string('pool_key')->unique();
            $table->unsignedInteger('last_user_id')->nullable();
            $table->foreign('last_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lead_distribution_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('selected_user_id')->nullable();
            $table->string('strategy');
            $table->string('pool_key');
            $table->json('candidate_user_ids');
            $table->string('result');
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('selected_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['lead_id', 'created_at']);
        });

        DB::table('lead_distribution_states')->insert([
            'pool_key' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_distribution_decisions');
        Schema::dropIfExists('lead_distribution_states');
    }
};
