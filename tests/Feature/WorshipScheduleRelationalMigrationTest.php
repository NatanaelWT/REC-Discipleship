<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorshipScheduleRelationalMigrationTest extends TestCase
{
    public function test_json_worship_schedules_are_backfilled_to_relational_tables(): void
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

        $migration = require database_path('migrations/2026_07_02_000001_normalize_worship_schedules_to_service_tables.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('worship_schedules'));
        foreach ([
            'worship_service_schedules',
            'worship_service_schedule_roles',
            'worship_service_schedule_weeks',
            'worship_service_assignments',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' should exist.');
        }

        $this->assertDatabaseHas('worship_service_schedules', [
            'id' => 12,
            'month' => '2026-06',
            'update_note' => 'Migrasi ibadah',
            'created_at' => '2026-06-02 09:10:27',
            'updated_at' => '2026-06-07 18:57:37',
        ]);
        $this->assertDatabaseCount('worship_service_schedule_roles', 2);
        $this->assertDatabaseHas('worship_service_schedule_roles', [
            'worship_service_schedule_id' => 12,
            'role_name' => 'LW',
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('worship_service_schedule_roles', [
            'worship_service_schedule_id' => 12,
            'role_name' => 'Singer',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseMissing('worship_service_schedule_roles', [
            'role_name' => 'Jadwal Latihan',
        ]);

        $this->assertDatabaseCount('worship_service_schedule_weeks', 4);
        $this->assertDatabaseHas('worship_service_schedule_weeks', [
            'worship_service_schedule_id' => 12,
            'week_index' => 0,
            'service_date' => '2026-06-07',
            'training_date' => '2026-06-05',
        ]);
        $this->assertDatabaseHas('worship_service_schedule_weeks', [
            'worship_service_schedule_id' => 12,
            'week_index' => 3,
            'service_date' => '2026-06-28',
            'training_date' => '2026-06-26',
        ]);

        $this->assertDatabaseCount('worship_service_assignments', 3);
        $this->assertDatabaseHas('worship_service_assignments', ['assignee_name' => 'Cia']);
        $this->assertDatabaseHas('worship_service_assignments', ['assignee_name' => 'Ryan']);
        $this->assertDatabaseHas('worship_service_assignments', ['assignee_name' => 'Zerren']);
    }

    private function createLegacyWorshipScheduleTable(): void
    {
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
