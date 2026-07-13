<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainTableRenameMigrationTest extends TestCase
{
    /** @var array<string, string> */
    private array $tables = [
        'app_configs' => 'konfigurasi',
        'branches' => 'cabang',
        'login_attempts' => 'percobaan_login',
        'difficult_questions' => 'pertanyaan_sulit',
        'people' => 'orang',
        'discipleship_groups' => 'kelompok_dg',
        'discipleship_group_people' => 'keanggotaan_kelompok_dg',
        'discipleship_relationships' => 'relasi_dg',
        'discipleship_meeting_reports' => 'jurnal_temu_dg',
        'discipleship_feedbacks' => 'jurnal_umpan_balik',
        'discipleship_manual_journey_records' => 'dg_manual',
        'public_material_files' => 'materi_publik',
        'worship_service_schedules' => 'jadwal_pelayanan_ibadah',
    ];

    public function test_domain_tables_are_renamed_to_indonesian_names_with_data_intact(): void
    {
        $this->dropTables();

        foreach (array_keys($this->tables) as $tableName) {
            Schema::create($tableName, static function (Blueprint $table): void {
                $table->id();
                $table->string('marker')->nullable();
            });
            DB::table($tableName)->insert(['id' => 1, 'marker' => $tableName]);
        }

        $migration = require database_path('migrations/2026_07_04_000003_rename_domain_tables_to_indonesian.php');
        $migration->up();

        foreach ($this->tables as $oldName => $newName) {
            $this->assertFalse(Schema::hasTable($oldName), $oldName.' should be renamed');
            $this->assertTrue(Schema::hasTable($newName), $newName.' should exist');
            $this->assertDatabaseHas($newName, ['id' => 1, 'marker' => $oldName]);
        }
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    private function dropTables(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($this->tables as $oldName => $newName) {
                Schema::dropIfExists($oldName);
                Schema::dropIfExists($newName);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
