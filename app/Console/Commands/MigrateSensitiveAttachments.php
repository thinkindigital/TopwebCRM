<?php

namespace App\Console\Commands;

use App\Services\SensitiveFileService;
use Illuminate\Console\Command;
use Throwable;
use Webkul\Activity\Models\File;
use Webkul\Email\Models\Attachment;

class MigrateSensitiveAttachments extends Command
{
    protected $signature = 'sensitive-data:migrate-attachments {--dry-run : Inspect files without changing storage}';

    protected $description = 'Move email and activity attachments from public to private storage';

    public function handle(SensitiveFileService $sensitiveFiles): int
    {
        $counts = [];
        $dryRun = (bool) $this->option('dry-run');

        foreach ([Attachment::class, File::class] as $model) {
            $model::query()
                ->select(['id', 'path'])
                ->whereNotNull('path')
                ->lazyById()
                ->each(function ($record) use (&$counts, $dryRun, $model, $sensitiveFiles) {
                    try {
                        $status = $dryRun
                            ? $sensitiveFiles->migrationStatus($record->path)
                            : $sensitiveFiles->migrateLegacy($record->path);
                    } catch (Throwable) {
                        $status = 'error';

                        $this->warn("Unable to process {$model} record #{$record->id}.");
                    }

                    $counts[$status] = ($counts[$status] ?? 0) + 1;
                });
        }

        ksort($counts);

        $this->table(
            ['Status', 'Files'],
            collect($counts)->map(fn ($count, $status) => [$status, $count])->values()->all()
        );

        if (isset($counts['conflict']) || isset($counts['error']) || isset($counts['missing'])) {
            $this->error('Some attachment records require manual review.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Inspection completed.' : 'Sensitive attachments migrated.');

        return self::SUCCESS;
    }
}
