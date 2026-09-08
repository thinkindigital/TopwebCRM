<?php

namespace Webkul\TopwebChat\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportRealEstateDemo extends Command
{
    protected $signature = 'topwebchat:import-real-estate-demo
        {csv? : CSV path. Defaults to packages/Webkul/TopwebChat/src/Database/demo/real-estate-demo.csv}
        {--user-email= : Existing user email used as owner for imported people and leads}';

    protected $description = 'Import additive real estate demo data from CSV without deleting users or roles';

    public function handle(): int
    {
        $csvPath = $this->argument('csv') ?: dirname(__DIR__, 2).'/Database/demo/real-estate-demo.csv';

        if (! is_file($csvPath)) {
            $this->error("CSV not found: {$csvPath}");

            return self::FAILURE;
        }

        $owner = $this->resolveOwner();
        $rows = $this->readCsv($csvPath);

        DB::transaction(function () use ($rows, $owner) {
            foreach ($rows as $row) {
                $this->importRow($row, (int) $owner->id);
            }
        });

        $this->info('Real estate demo data imported without deleting users or roles.');
        $this->table(['Imported rows', 'Owner'], [[count($rows), $owner->email]]);

        return self::SUCCESS;
    }

    private function resolveOwner(): object
    {
        $query = DB::table('users')->where('status', 1);

        if ($email = $this->option('user-email')) {
            $query->where('email', $email);
        }

        $owner = $query->orderBy('id')->first();

        if (! $owner) {
            throw new RuntimeException('No active user found for imported demo data.');
        }

        return $owner;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV: {$path}");
        }

        $headers = fgetcsv($handle);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if ($values === [null] || $values === false) {
                continue;
            }

            $rows[] = array_combine($headers, $values);
        }

        fclose($handle);

        return $rows;
    }

    private function importRow(array $row, int $ownerId): void
    {
        $now = Carbon::now();
        $pipelineId = $this->idForName('lead_pipelines', $row['pipeline'], ['is_default' => 0, 'rotten_days' => 0]);
        $pipelineStageId = $this->pipelineStageId($pipelineId, $row['stage_code'], $row['stage']);
        $sourceId = $this->idForName('lead_sources', $row['source']);
        $typeId = $this->idForName('lead_types', $row['type']);
        $personId = $this->personId($row, $ownerId, $now);

        DB::table('products')->updateOrInsert(
            ['sku' => $row['product_sku']],
            [
                'name' => $row['product_name'],
                'description' => $row['product_description'],
                'quantity' => 1,
                'price' => (float) $row['value'],
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('leads')->updateOrInsert(
            ['title' => $row['lead_title']],
            [
                'description' => $row['lead_description'],
                'lead_value' => (float) $row['value'],
                'status' => 1,
                'user_id' => $ownerId,
                'person_id' => $personId,
                'lead_source_id' => $sourceId,
                'lead_type_id' => $typeId,
                'lead_pipeline_id' => $pipelineId,
                'lead_pipeline_stage_id' => $pipelineStageId,
                'expected_close_date' => now()->addDays((int) $row['expected_close_days'])->toDateString(),
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    private function idForName(string $table, string $name, array $extra = []): int
    {
        $record = DB::table($table)->where('name', $name)->first();

        if ($record) {
            return (int) $record->id;
        }

        return (int) DB::table($table)->insertGetId(array_merge($extra, [
            'name' => $name,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]));
    }

    private function pipelineStageId(int $pipelineId, string $code, string $name): int
    {
        $record = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', $code)
            ->first();

        if ($record) {
            return (int) $record->id;
        }

        return (int) DB::table('lead_pipeline_stages')->insertGetId([
            'code' => $code,
            'name' => $name,
            'probability' => 10,
            'sort_order' => 1,
            'lead_pipeline_id' => $pipelineId,
        ]);
    }

    private function personId(array $row, int $ownerId, Carbon $now): int
    {
        $record = DB::table('persons')->where('emails', 'like', '%"'.$row['person_email'].'"%')->first();
        $payload = [
            'name' => $row['person_name'],
            'emails' => json_encode([['value' => $row['person_email'], 'label' => 'work']]),
            'contact_numbers' => json_encode([['value' => $row['phone'], 'label' => 'mobile']]),
            'updated_at' => $now,
        ];

        if ($record) {
            DB::table('persons')->where('id', $record->id)->update($payload);

            return (int) $record->id;
        }

        return (int) DB::table('persons')->insertGetId(array_merge($payload, [
            'created_at' => $now,
        ]));
    }
}
