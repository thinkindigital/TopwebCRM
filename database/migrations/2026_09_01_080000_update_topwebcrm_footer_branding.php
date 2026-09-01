<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_config')) {
            return;
        }

        DB::table('core_config')
            ->where('code', 'general.settings.footer.label')
            ->where(function ($query) {
                $query->where('value', 'like', '%Webkul%')
                    ->orWhere('value', 'like', '%Krayin%');
            })
            ->update([
                'value' => 'Desenvolvido por <a style="color: rgb(14, 144, 217);" href="https://topwebdigital.com.br" target="_blank" rel="noopener">Topweb Digital</a>.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Branding changes are intentionally not reverted to third-party copy.
    }
};
