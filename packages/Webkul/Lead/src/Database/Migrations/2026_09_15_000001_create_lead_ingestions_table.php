<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_ingestions', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('source_lead_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('person_id')->nullable();
            $table->timestamps();

            $table->index(['source', 'source_lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_ingestions');
    }
};
