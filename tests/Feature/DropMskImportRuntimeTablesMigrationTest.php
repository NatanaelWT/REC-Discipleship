<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DropMskImportRuntimeTablesMigrationTest extends TestCase
{
    /** @var list<string> */
    private const TABLES = [
        'msk_import_batches',
        'msk_import_existing_people',
        'msk_import_source_keys',
        'msk_import_jobs',
    ];

    protected function tearDown(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_migration_drops_terminal_import_state_child_first(): void
    {
        $this->createOriginalSchema();
        $jobId = $this->insertJob('completed');
        $this->insertChildRows($jobId);

        $this->migration()->up();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "MSK import table [{$table}] was not dropped.");
        }
    }

    public function test_migration_refuses_to_drop_a_pending_job(): void
    {
        $this->assertActiveJobBlocksMigration('pending');
    }

    public function test_migration_refuses_to_drop_a_running_job(): void
    {
        $this->assertActiveJobBlocksMigration('running');
    }

    public function test_migration_fails_closed_for_an_unknown_non_terminal_status(): void
    {
        $this->assertActiveJobBlocksMigration('queued');
    }

    public function test_migration_is_idempotent_when_the_runtime_tables_are_already_absent(): void
    {
        $migration = $this->migration();

        $migration->up();
        $migration->up();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_migration_removes_a_partial_legacy_schema_without_jobs_table(): void
    {
        Schema::create('msk_import_source_keys', function ($table): void {
            $table->id();
            $table->string('match_key', 64);
        });
        Schema::create('msk_import_batches', function ($table): void {
            $table->id();
            $table->string('batch_token', 100);
        });

        $this->migration()->up();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_down_recreates_the_exact_original_empty_schema(): void
    {
        $this->createOriginalSchema();
        $expected = $this->schemaSnapshot();
        $jobId = $this->insertJob('failed');
        $this->insertChildRows($jobId);

        $migration = $this->migration();
        $migration->up();
        $migration->down();

        $this->assertSame($expected, $this->schemaSnapshot());
        foreach (self::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Rollback recreated non-empty table [{$table}].");
        }
    }

    public function test_normal_migration_path_leaves_migrate_fresh_without_import_runtime_tables(): void
    {
        $exitCode = Artisan::call('migrate:fresh', [
            '--path' => ['database/migrations'],
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "MSK import table [{$table}] survived migrate:fresh.");
        }
    }

    private function assertActiveJobBlocksMigration(string $status): void
    {
        $this->createOriginalSchema();
        $jobId = $this->insertJob($status);
        $this->insertChildRows($jobId);

        try {
            $this->migration()->up();
            $this->fail("Migration accepted an active [{$status}] import job.");
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('job non-terminal/aktif masih ada', $exception->getMessage());
        }

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Guard partially dropped [{$table}].");
            $this->assertSame(1, DB::table($table)->count(), "Guard changed rows in [{$table}].");
        }
    }

    private function createOriginalSchema(): void
    {
        $path = database_path('migrations/2026_07_13_120000_create_msk_import_jobs.php');
        $this->assertFileExists($path);

        /** @var Migration $migration */
        $migration = require $path;
        $migration->up();
    }

    private function insertJob(string $status): string
    {
        $jobId = (string) Str::ulid();
        DB::table('msk_import_jobs')->insert([
            'id' => $jobId,
            'user_id' => 1,
            'branch_id' => 1,
            'active_branch_id' => in_array($status, ['pending', 'running'], true) ? 1 : null,
            'idempotency_token' => 'migration-test-'.$status,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $jobId;
    }

    private function insertChildRows(string $jobId): void
    {
        DB::table('msk_import_source_keys')->insert([
            'job_id' => $jobId,
            'row_number' => 2,
            'match_type' => 'participant',
            'match_key' => '1',
        ]);
        DB::table('msk_import_existing_people')->insert([
            'job_id' => $jobId,
            'person_id' => 1,
        ]);
        DB::table('msk_import_batches')->insert([
            'job_id' => $jobId,
            'batch_token' => 'migration-test-batch',
            'byte_cursor_before' => 0,
            'byte_cursor_after' => 10,
            'row_count' => 1,
            'result' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function schemaSnapshot(): array
    {
        $snapshot = [];
        foreach (self::TABLES as $table) {
            $snapshot[$table] = [
                'columns' => Schema::getColumns($table),
                'indexes' => Schema::getIndexes($table),
                'foreign_keys' => Schema::getForeignKeys($table),
            ];
        }

        return $snapshot;
    }

    private function migration(): Migration
    {
        $path = database_path('migrations/2026_07_20_000002_drop_msk_import_runtime_tables.php');
        $this->assertFileExists($path);

        /** @var Migration $migration */
        $migration = require $path;

        return $migration;
    }
}
