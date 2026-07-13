<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class VerifyRuntimeSchema extends Command
{
    protected $signature = 'rec:schema-health {--json : Print a machine-readable result}';

    protected $description = 'Verify the migration-backed runtime schema contract before enabling traffic';

    public function handle(): int
    {
        /** @var array<string, array<int, string>> $contract */
        $contract = config('runtime_schema.tables', []);
        $missingTables = [];
        $missingColumns = [];
        $error = null;

        try {
            foreach ($contract as $table => $requiredColumns) {
                if (! Schema::hasTable($table)) {
                    $missingTables[] = $table;

                    continue;
                }

                $available = array_fill_keys(Schema::getColumnListing($table), true);
                foreach ($requiredColumns as $column) {
                    if (! isset($available[$column])) {
                        $missingColumns[] = $table.'.'.$column;
                    }
                }
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $healthy = $error === null && $missingTables === [] && $missingColumns === [];
        $result = [
            'healthy' => $healthy,
            'checked_tables' => count($contract),
            'missing_tables' => $missingTables,
            'missing_columns' => $missingColumns,
            'error' => $error,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $healthy ? self::SUCCESS : self::FAILURE;
        }

        if ($healthy) {
            $this->info('Kontrak schema runtime lengkap ('.count($contract).' tabel).');

            return self::SUCCESS;
        }

        $this->error('Kontrak schema runtime belum siap. Traffic tidak boleh diaktifkan.');
        if ($missingTables !== []) {
            $this->line('Tabel hilang: '.implode(', ', $missingTables));
        }
        if ($missingColumns !== []) {
            $this->line('Kolom hilang: '.implode(', ', $missingColumns));
        }
        if ($error !== null) {
            $this->line('Error: '.$error);
        }

        return self::FAILURE;
    }
}
