<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorshipScheduleTitleMigrationTest extends TestCase
{
    public function test_title_columns_are_removed_without_losing_schedule_data(): void
    {
        foreach (['worship_schedules', 'worship_service_schedules'] as $tableName) {
            Schema::dropIfExists($tableName);
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('month', 7)->unique();
                $table->string('title');
                $table->text('update_note')->nullable();
                $table->json('rows')->nullable();
                $table->timestamps();
            });

            DB::table($tableName)->insert([
                'month' => '2026-06',
                'title' => 'Judul lama',
                'update_note' => 'Tetap tersimpan',
                'rows' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_06_25_000001_remove_title_from_worship_schedules.php');
        $migration->up();

        foreach (['worship_schedules', 'worship_service_schedules'] as $tableName) {
            $this->assertFalse(Schema::hasColumn($tableName, 'title'));
            $this->assertDatabaseHas($tableName, [
                'month' => '2026-06',
                'update_note' => 'Tetap tersimpan',
            ]);
        }
    }
}
