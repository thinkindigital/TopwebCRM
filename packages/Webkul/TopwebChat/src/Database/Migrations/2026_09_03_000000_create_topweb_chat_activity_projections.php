<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topweb_chat_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('topweb_chat_conversations')
                ->cascadeOnDelete();
            $table->unsignedInteger('activity_id');
            $table->unsignedInteger('sequence');
            $table->foreignId('opened_by_message_id')
                ->nullable()
                ->constrained('topweb_chat_messages')
                ->nullOnDelete();
            $table->foreignId('last_message_id')
                ->nullable()
                ->constrained('topweb_chat_messages')
                ->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('last_real_message_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('activity_id')
                ->references('id')
                ->on('activities')
                ->cascadeOnDelete();
            $table->unique(['conversation_id', 'sequence']);
            $table->unique('activity_id');
            $table->index(['closed_at', 'last_real_message_at']);
        });

        Schema::create('topweb_chat_media_projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')
                ->nullable()
                ->unique()
                ->constrained('topweb_chat_messages')
                ->nullOnDelete();
            $table->unsignedInteger('activity_id')->unique();
            $table->unsignedInteger('activity_file_id')->unique();
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('person_id')->nullable();
            $table->timestamps();

            $table->foreign('activity_id')
                ->references('id')
                ->on('activities')
                ->cascadeOnDelete();
            $table->foreign('activity_file_id')
                ->references('id')
                ->on('activity_files')
                ->cascadeOnDelete();
            $table->foreign('lead_id')
                ->references('id')
                ->on('leads')
                ->nullOnDelete();
            $table->foreign('person_id')
                ->references('id')
                ->on('persons')
                ->nullOnDelete();
            $table->index(['lead_id', 'created_at']);
            $table->index(['person_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topweb_chat_media_projections');
        Schema::dropIfExists('topweb_chat_attendances');
    }
};
