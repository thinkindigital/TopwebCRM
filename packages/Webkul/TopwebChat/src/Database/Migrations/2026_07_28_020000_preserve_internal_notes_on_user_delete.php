<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topweb_chat_internal_notes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('topweb_chat_internal_notes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        DB::table('topweb_chat_internal_notes')
            ->whereNull('user_id')
            ->delete();

        Schema::table('topweb_chat_internal_notes', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
