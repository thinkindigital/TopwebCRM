<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topweb_chat_conversations', function (Blueprint $table) {
            $table->timestamp('history_cursor_at')->nullable()->after('last_message_at');
            $table->timestamp('history_backfilled_at')->nullable()->after('history_cursor_at');
        });
    }

    public function down(): void
    {
        Schema::table('topweb_chat_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'history_cursor_at',
                'history_backfilled_at',
            ]);
        });
    }
};
