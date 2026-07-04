<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorshipScheduleRelationalMigrationTest extends TestCase
{
    public function test_json_worship_schedules_are_backfilled_to_weekly_service_schedule_rows(): void
    {
        $this->createLegacyWorshipScheduleTable();

        DB::table('worship_schedules')->insert([
            'id' => 12,
            'month' => '2026-06',
            'update_note' => 'Migrasi ibadah',
            'rows' => json_encode([
                ['role' => 'LW', 'assignments' => ['Cia', '', '', '']],
                ['role' => 'Singer', 'assignments' => ["Ryan\nZerren", '', '', '']],
                ['role' => 'Jadwal Latihan', 'assignments' => ['2026-06-05', '', '', '2026-06-26']],
            ]),
            'created_at' => '2026-06-02 09:10:27',
            'updated_at' => '2026-06-07 18:57:37',
        ]);

        $relationalMigration = require database_path('migrations/2026_07_02_000001_normalize_worship_schedules_to_service_tables.php');
        $relationalMigration->up();

        $flatMigration = require database_path('migrations/2026_07_04_000001_flatten_worship_service_schedules.php');
        $flatMigration->up();

        $weeklyMigration = require database_path('migrations/2026_07_04_000002_store_worship_service_schedules_by_week.php');
        $weeklyMigration->up();

        $this->assertFalse(Schema::hasTable('worship_schedules'));
        $this->assertFalse(Schema::hasTable('worship_service_schedules_flat'));
        foreach ([
            'worship_service_schedule_roles',
            'worship_service_schedule_weeks',
            'worship_service_assignments',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table.' should not exist.');
        }

        $this->assertTrue(Schema::hasTable('worship_service_schedules'));
        $this->assertTrue(Schema::hasColumn('worship_service_schedules', 'lw_1_name'));
        $this->assertTrue(Schema::hasColumn('worship_service_schedules', 'singer_2_name'));
        $this->assertFalse(Schema::hasColumn('worship_service_schedules', 'row_type'));
        $this->assertFalse(Schema::hasColumn('worship_service_schedules', 'assignee_name'));
        $this->assertFalse(Schema::hasColumn('worship_service_schedules', 'rows'));

        $this->assertDatabaseCount('worship_service_schedules', 4);
        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'update_note' => 'Migrasi ibadah',
            'week_index' => 0,
            'service_date' => '2026-06-07',
            'training_date' => '2026-06-05',
            'lw_1_name' => 'Cia',
            'singer_1_name' => 'Ryan',
            'singer_2_name' => 'Zerren',
            'created_at' => '2026-06-02 09:10:27',
            'updated_at' => '2026-06-07 18:57:37',
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'week_index' => 1,
            'service_date' => '2026-06-14',
            'lw_1_name' => null,
            'singer_1_name' => null,
            'singer_2_name' => null,
            'training_date' => null,
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'week_index' => 3,
            'service_date' => '2026-06-28',
            'training_date' => '2026-06-26',
        ]);
    }

    private function createLegacyWorshipScheduleTable(): void
    {
        Schema::dropIfExists('worship_service_schedules_weekly');
        Schema::dropIfExists('worship_service_schedules_flat');
        Schema::dropIfExists('worship_service_assignments');
        Schema::dropIfExists('worship_service_schedule_weeks');
        Schema::dropIfExists('worship_service_schedule_roles');
        Schema::dropIfExists('worship_service_schedules');
        Schema::dropIfExists('worship_schedules');

        Schema::create('worship_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->unique();
            $table->longText('update_note')->nullable();
            $table->json('rows')->nullable();
            $table->timestamps();
        });
    }
}
