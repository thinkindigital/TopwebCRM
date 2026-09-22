<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topweb_chat_messages', function (Blueprint $table) {
            $table->string('error_code', 32)->nullable()->after('last_error');
            $table->string('trace_id', 26)->nullable()->after('error_code')->index();
        });

        Schema::table('topweb_chat_webhook_events', function (Blueprint $table) {
            $table->string('error_code', 32)->nullable()->after('last_error');
            $table->string('trace_id', 26)->nullable()->after('error_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('topweb_chat_webhook_events', function (Blueprint $table) {
            $table->dropIndex(['trace_id']);
            $table->dropColumn(['error_code', 'trace_id']);
        });

        Schema::table('topweb_chat_messages', function (Blueprint $table) {
            $table->dropIndex(['trace_id']);
            $table->dropColumn(['error_code', 'trace_id']);
        });
    }
};
