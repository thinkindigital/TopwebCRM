<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_distribution_decisions', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('lead_distribution_decisions', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
