<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateDiscipleshipTargetsToLaravelTable extends Command
{
    protected $signature = 'rec:migrate-discipleship-targets {--dry-run : Count rows without writing}';

    protected $description = 'Migrate discipleship target records from rec_discipleship_targets to discipleship_targets.';

    public function handle(): int
    {
        if (! Schema::hasTable('rec_discipleship_targets')) {
            $this->warn('Source table rec_discipleship_targets does not exist.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('discipleship_targets')) {
            $this->error('Target table discipleship_targets does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $rows = DB::table('rec_discipleship_targets')->orderBy('id')->get();
        if ($this->option('dry-run')) {
            $this->info('Rows ready to migrate: ' . $rows->count());

            return self::SUCCESS;
        }

        $migrated = 0;
        foreach ($rows as $row) {
            $branchCode = $this->branchCode($row->branch ?? '');
            if ($branchCode === '') {
                continue;
            }

            DB::table('discipleship_targets')->updateOrInsert(
                ['branch_code' => $branchCode],
                [
                    'camp_gap_participant_target' => $this->boundedInteger($row->dg_total_people ?? 50),
                    'msk_completion_target' => $this->boundedInteger($row->msk_completed ?? 50),
                    'dg1_completion_target' => $this->boundedInteger($row->dg1_people ?? 50),
                    'dg2_completion_target' => $this->boundedInteger($row->dg2_people ?? 50),
                    'dg3_completion_target' => $this->boundedInteger($row->dg3_people ?? 50),
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ],
            );
            $migrated++;
        }

        $this->info("Migrated {$migrated} discipleship target rows.");

        return self::SUCCESS;
    }

    private function branchCode(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';
    }

    private function boundedInteger(mixed $value): int
    {
        if (is_string($value)) {
            $value = preg_replace('/[^0-9]/', '', $value) ?? '';
        }

        if (! is_numeric($value)) {
            $value = 50;
        }

        return min(1000000, max(0, (int) $value));
    }
}
