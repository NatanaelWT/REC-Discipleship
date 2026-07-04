<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorshipScheduleRelationalMigrationTest extends TestCase
{
    public function test_json_worship_schedules_are_backfilled_to_flat_service_schedule_rows(): void
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

        $this->assertFalse(Schema::hasTable('worship_schedules'));
        foreach ([
            'worship_service_schedule_roles',
            'worship_service_schedule_weeks',
            'worship_service_assignments',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table.' should not exist.');
        }

        $this->assertTrue(Schema::hasTable('worship_service_schedules'));
        $this->assertTrue(Schema::hasColumn('worship_service_schedules', 'row_type'));
        $this->assertTrue(Schema::hasColumn('worship_service_schedules', 'assignee_sort_order'));
        $this->assertFalse(Schema::hasColumn('worship_service_schedules', 'rows'));

        $this->assertDatabaseCount('worship_service_schedules', 13);
        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'update_note' => 'Migrasi ibadah',
            'row_type' => 'assignment',
            'role_name' => 'LW',
            'role_sort_order' => 0,
            'week_index' => 0,
            'service_date' => '2026-06-07',
            'assignee_name' => 'Cia',
            'assignee_sort_order' => 0,
            'created_at' => '2026-06-02 09:10:27',
            'updated_at' => '2026-06-07 18:57:37',
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'role_name' => 'LW',
            'week_index' => 1,
            'assignee_name' => null,
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'role_name' => 'Singer',
            'week_index' => 0,
            'assignee_name' => 'Ryan',
            'assignee_sort_order' => 0,
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'role_name' => 'Singer',
            'week_index' => 0,
            'assignee_name' => 'Zerren',
            'assignee_sort_order' => 1,
        ]);
        $this->assertDatabaseMissing('worship_service_schedules', [
            'assignee_name' => "Ryan\nZerren",
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'row_type' => 'training',
            'role_name' => 'Jadwal Latihan',
            'week_index' => 0,
            'service_date' => '2026-06-07',
            'training_date' => '2026-06-05',
            'assignee_name' => null,
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'row_type' => 'training',
            'week_index' => 3,
            'service_date' => '2026-06-28',
            'training_date' => '2026-06-26',
        ]);
    }

    private function createLegacyWorshipScheduleTable(): void
    {
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
