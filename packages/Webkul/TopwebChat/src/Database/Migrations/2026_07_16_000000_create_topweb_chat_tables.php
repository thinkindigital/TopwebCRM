<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_view_sensitive_data')->default(false)->after('view_permission');
        });

        Schema::create('topweb_chat_instances', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('provider')->default('ryzeapi');
            $table->text('token');
            $table->text('webhook_secret');
            $table->string('status')->default('unknown');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('topweb_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('topweb_chat_instances')->cascadeOnDelete();
            $table->unsignedInteger('person_id');
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->unsignedInteger('assigned_group_id')->nullable();
            $table->text('remote_jid');
            $table->char('remote_jid_key', 64);
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_group_id')->references('id')->on('groups')->nullOnDelete();
            $table->unique(['instance_id', 'remote_jid_key'], 'topweb_chat_conversation_remote_unique');
            $table->index(['assigned_user_id', 'status']);
            $table->index(['assigned_group_id', 'status']);
            $table->index(['status', 'last_message_at']);
        });

        Schema::create('topweb_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('topweb_chat_conversations')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->text('provider_message_id')->nullable();
            $table->char('provider_message_key', 64)->nullable()->unique();
            $table->string('direction');
            $table->string('type')->default('text');
            $table->longText('content')->nullable();
            $table->string('status')->default('pending');
            $table->string('source')->default('topweb_chat');
            $table->longText('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['conversation_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('topweb_chat_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('topweb_chat_conversations')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->text('content');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('topweb_chat_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('topweb_chat_instances')->cascadeOnDelete();
            $table->char('event_key', 64)->unique();
            $table->string('event_type');
            $table->longText('payload');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topweb_chat_webhook_events');
        Schema::dropIfExists('topweb_chat_internal_notes');
        Schema::dropIfExists('topweb_chat_messages');
        Schema::dropIfExists('topweb_chat_conversations');
        Schema::dropIfExists('topweb_chat_instances');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_view_sensitive_data');
        });
    }
};
