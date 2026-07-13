<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 * RELEASE-2 DESTRUCTIVE MIGRATION.
 *
 * This migration permanently removes every current and legacy tracking table.
 * Its down migration deliberately cannot reconstruct deleted data; restoring a
 * paired Release-2 snapshot is the only supported rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            // Child/event tables must be removed before their parent request/session tables.
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

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        $remaining = array_values(array_filter(
            $tables,
            static fn (string $table): bool => Schema::hasTable($table),
        ));
        if ($remaining !== []) {
            throw new RuntimeException(
                'Penghapusan tabel tracking belum lengkap: '.implode(', ', $remaining),
            );
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migration penghapusan activity/analytics tidak dapat dibatalkan. Pulihkan snapshot database Release 2.',
        );
    }
};
