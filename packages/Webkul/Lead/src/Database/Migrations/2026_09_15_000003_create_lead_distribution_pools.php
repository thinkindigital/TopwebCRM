<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_distribution_pools', function (Blueprint $table) {
            $table->id();
            $table->string('pool_key')->unique();
            $table->string('strategy');
            $table->unsignedInteger('fallback_user_id')->nullable();
            $table->json('constraints')->nullable();
            $table->timestamps();

            $table->foreign('fallback_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('lead_distribution_pool_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('lead_distribution_pools')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->boolean('enabled')->default(false);
            $table->string('region')->nullable();
            $table->decimal('score', 10, 3)->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['pool_id', 'user_id']);
            $table->index(['pool_id', 'enabled', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_distribution_pool_users');
        Schema::dropIfExists('lead_distribution_pools');
    }
};
