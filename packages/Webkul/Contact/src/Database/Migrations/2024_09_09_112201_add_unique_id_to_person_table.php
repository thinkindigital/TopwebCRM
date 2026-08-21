<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column exists before adding
        $columns = Schema::getColumnListing('persons');
        if (! in_array('unique_id', $columns)) {
            Schema::table('persons', function (Blueprint $table) {
                $table->string('unique_id')->nullable()->unique();
            });
        }

        // Skip JSON operations for SQLite as it doesn't support JSON_UNQUOTE/JSON_EXTRACT
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $tableName = DB::getTablePrefix().'persons';

        DB::statement("
            UPDATE {$tableName}
            SET unique_id = CONCAT(
                user_id, '|', 
                organization_id, '|', 
                JSON_UNQUOTE(JSON_EXTRACT(emails, '\$[0].value')), '|',
                JSON_UNQUOTE(JSON_EXTRACT(contact_numbers, '\$[0].value'))
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            if (Schema::hasColumn('persons', 'unique_id')) {
                $table->dropColumn('unique_id');
            }
        });
    }
};
