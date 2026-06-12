<?php

namespace App\Console\Commands;

use App\Services\WorshipServiceSchedules\WorshipServiceScheduleBuilder;
use App\Services\WorshipServiceSchedules\WorshipServiceScheduleNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateWorshipServiceSchedulesToLaravelTables extends Command
{
    protected $signature = 'rec:migrate-worship-service-schedules {--dry-run : Count rows without writing}';

    protected $description = 'Migrate worship penatalayan schedules from rec_worship_penatalayan_schedules to normalized Laravel tables.';

    public function handle(
        WorshipServiceScheduleNormalizer $normalizer,
        WorshipServiceScheduleBuilder $builder,
    ): int {
        if (! Schema::hasTable('rec_worship_penatalayan_schedules')) {
            $this->warn('Source table rec_worship_penatalayan_schedules does not exist.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('worship_service_schedules')) {
            $this->error('Target table worship_service_schedules does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $rows = DB::table('rec_worship_penatalayan_schedules')->orderBy('month')->get();
        if ($this->option('dry-run')) {
            $this->info('Rows ready to migrate: ' . $rows->count());

            return self::SUCCESS;
        }

        $migrated = 0;
        foreach ($rows as $row) {
            $record = $normalizer->fromLegacyRow($row);
            $builder->saveRecord($record, preserveTimestamps: true);
            $migrated++;
        }

        $this->info("Migrated {$migrated} worship service schedule rows.");

        return self::SUCCESS;
    }
}
