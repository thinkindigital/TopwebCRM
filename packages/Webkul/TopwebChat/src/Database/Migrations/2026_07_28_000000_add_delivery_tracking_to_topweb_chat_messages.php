<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topweb_chat_messages', function (Blueprint $table) {
            $table->uuid('operation_key')->nullable()->unique()->after('user_id');
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->string('last_error', 120)->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('topweb_chat_messages', function (Blueprint $table) {
            $table->dropUnique(['operation_key']);
            $table->dropColumn(['operation_key', 'attempts', 'last_error']);
        });
    }
};
