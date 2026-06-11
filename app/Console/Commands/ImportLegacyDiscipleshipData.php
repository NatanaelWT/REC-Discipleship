<?php

namespace App\Console\Commands;

use App\Support\LegacyDataStore;
use Illuminate\Console\Command;

class ImportLegacyDiscipleshipData extends Command
{
    protected $signature = 'rec:legacy:import';

    protected $description = 'Import the copied Discipleship JSON files into the Laravel MySQL source tables.';

    public function handle(): int
    {
        LegacyDataStore::ensureRuntimeFilesystem();

        if (! LegacyDataStore::databaseReady()) {
            $this->error('REC source tables are not ready. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $count = LegacyDataStore::syncFilesToDatabase();
        LegacyDataStore::syncDatabaseToFiles();

        $this->info("Imported {$count} legacy JSON file(s) into dedicated MySQL tables.");

        return self::SUCCESS;
    }
}
