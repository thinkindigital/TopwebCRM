<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topweb_chat_instances', function (Blueprint $table) {
            // SQLite doesn't support ->change() directly, so we add new columns
            if (!Schema::hasColumn('topweb_chat_instances', 'provider')) {
                $table->string('provider')->default('openwa');
            }
            if (!Schema::hasColumn('topweb_chat_instances', 'session_uuid')) {
                $table->string('session_uuid')->nullable()->after('provider');
            }
            if (!Schema::hasColumn('topweb_chat_instances', 'base_url')) {
                $table->text('base_url')->nullable()->after('session_uuid');
            }
            if (!Schema::hasColumn('topweb_chat_instances', 'engine_loaded')) {
                $table->boolean('engine_loaded')->default(false)->after('status');
            }
            if (!Schema::hasColumn('topweb_chat_instances', 'restriction')) {
                $table->json('restriction')->nullable()->after('engine_loaded');
            }
            // SQLite doesn't support ->change() on timestamps easily, skip if they exist
        });
    }

    public function down(): void
    {
        Schema::table('topweb_chat_instances', function (Blueprint $table) {
            $table->dropColumn([
                'session_uuid',
                'base_url',
                'engine_loaded',
                'restriction',
            ]);
            // SQLite doesn't support ->change() on provider
        });
    }
};