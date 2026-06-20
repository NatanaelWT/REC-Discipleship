<?php

namespace Tests\Feature;

use App\Services\WorshipServiceSchedules\WorshipServiceScheduleBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorshipGlobalScopeTest extends TestCase
{
    public function test_steward_and_developer_share_the_same_global_schedule(): void
    {
        $this->createWorshipScheduleTable();
        DB::table('worship_schedules')->insert([
            'month' => '2026-06',
            'title' => 'Jadwal Global',
            'update_note' => null,
            'rows' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsRecUser('keziaae', null, 'pelayan');
        $builder = app(WorshipServiceScheduleBuilder::class);
        $this->assertSame('Jadwal Global', $builder->recordForMonth('2026-06')['title'] ?? null);

        $this->actingAsRecUser('developer', null, 'developer');
        $builder->saveRecord([
            'month' => '2026-06',
            'title' => 'Jadwal Global Diperbarui',
            'rows' => [],
        ]);

        $this->assertDatabaseCount('worship_schedules', 1);
        $this->assertDatabaseHas('worship_schedules', [
            'month' => '2026-06',
            'title' => 'Jadwal Global Diperbarui',
        ]);
    }

    private function createWorshipScheduleTable(): void
    {
        Schema::dropIfExists('worship_schedules');
        Schema::create('worship_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->unique();
            $table->string('title');
            $table->string('update_note')->nullable();
            $table->json('rows')->nullable();
            $table->timestamps();
        });
    }
}
