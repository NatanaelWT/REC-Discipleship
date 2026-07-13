<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DropTrackingTablesMigrationTest extends TestCase
{
    /** @var list<string> */
    private const TRACKING_TABLES = [
        'audit_events',
        'activity_events',
        'peristiwa_aktivitas',
        'website_page_views',
        'kunjungan_halaman',
        'website_daily_rollups',
        'maintenance_runs',
        'request_activities',
        'activity_requests',
        'permintaan_aktivitas',
        'website_sessions',
        'sesi',
        'aktivitas',
    ];

    public function test_release_two_migration_drops_populated_current_and_legacy_tracking_tables(): void
    {
        foreach (self::TRACKING_TABLES as $table) {
            Schema::create($table, static function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('payload')->nullable();
            });
            DB::table($table)->insert(['payload' => 'must be deleted']);
        }

        $this->migration()->up();

        foreach (self::TRACKING_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "Tracking table [{$table}] was not dropped.");
        }
    }

    public function test_release_two_migration_is_idempotent_for_partial_schemas(): void
    {
        foreach (['audit_events', 'kunjungan_halaman', 'aktivitas'] as $table) {
            Schema::create($table, static function (Blueprint $blueprint): void {
                $blueprint->id();
            });
        }

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        foreach (self::TRACKING_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_release_two_migration_cannot_fake_a_data_rollback(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pulihkan snapshot database Release 2');

        $this->migration()->down();
    }

    public function test_release_two_migration_path_set_leaves_migrate_fresh_without_tracking_tables(): void
    {
        $paths = ['database/migrations'];
        if (is_dir(database_path('migrations/deferred'))) {
            $paths[] = 'database/migrations/deferred';
        }

        $exitCode = Artisan::call('migrate:fresh', [
            '--path' => $paths,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        foreach (self::TRACKING_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "Tracking table [{$table}] survived migrate:fresh.");
        }
    }

    private function migration(): Migration
    {
        $paths = [
            database_path('migrations/2026_07_20_000001_drop_activity_analytics_and_maintenance_tables.php'),
            database_path('migrations/deferred/2026_07_20_000001_drop_activity_analytics_and_maintenance_tables.php'),
        ];
        $path = collect($paths)->first(static fn (string $candidate): bool => is_file($candidate));
        $this->assertIsString($path, 'Release 2 tracking-table migration is missing.');

        /** @var Migration $migration */
        $migration = require $path;

        return $migration;
    }
}
