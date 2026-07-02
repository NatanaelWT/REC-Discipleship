<?php

namespace Tests\Feature;

use App\Services\WorshipServiceSchedules\WorshipServiceScheduleBuilder;
use App\Support\HelperManifest;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorshipGlobalScopeTest extends TestCase
{
    public function test_worship_routes_load_the_shared_month_normalizer(): void
    {
        $this->assertContains('normalize_month_value', HelperManifest::forPath('ibadah/penatalayan'));
    }

    public function test_export_title_is_generated_from_the_selected_month(): void
    {
        RuntimeBootstrap::load();

        $this->assertSame(
            'Jadwal Pelayanan Ibadah Umum Juni 2026',
            default_worship_penatalayan_title('2026-06'),
        );
    }

    public function test_steward_and_developer_share_the_same_global_schedule(): void
    {
        $this->createWorshipServiceScheduleTables();
        DB::table('worship_service_schedules')->insert([
            'month' => '2026-06',
            'update_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsRecUser('keziaae', null, 'pelayan');
        $builder = app(WorshipServiceScheduleBuilder::class);
        $this->assertArrayNotHasKey('title', $builder->recordForMonth('2026-06') ?? []);

        $this->actingAsRecUser('developer', null, 'developer');
        $builder->saveRecord([
            'month' => '2026-06',
            'update_note' => 'Diperbarui developer',
            'rows' => [],
        ]);

        $this->assertFalse(Schema::hasTable('worship_schedules'));
        $this->assertDatabaseCount('worship_service_schedules', 1);
        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'update_note' => 'Diperbarui developer',
        ]);
    }

    public function test_worship_schedule_save_replace_and_delete_use_relational_tables(): void
    {
        $this->createWorshipServiceScheduleTables();
        $builder = app(WorshipServiceScheduleBuilder::class);

        $builder->saveRecord([
            'month' => '2026-06',
            'update_note' => 'Catatan awal',
            'rows' => [
                ['role' => 'LW', 'assignments' => ['Cia', '', '', '']],
                ['role' => 'Singer', 'assignments' => ["Ryan\nZerren", '', '', '']],
                ['role' => 'Jadwal Latihan', 'assignments' => ['2026-06-05', '', '', '']],
            ],
        ]);

        $this->assertDatabaseCount('worship_service_schedules', 1);
        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'update_note' => 'Catatan awal',
        ]);
        $this->assertDatabaseCount('worship_service_schedule_weeks', 4);
        $this->assertDatabaseHas('worship_service_schedule_weeks', [
            'week_index' => 0,
            'service_date' => '2026-06-07',
            'training_date' => '2026-06-05',
        ]);
        $this->assertDatabaseHas('worship_service_schedule_roles', ['role_name' => 'LW']);
        $this->assertDatabaseHas('worship_service_assignments', ['assignee_name' => 'Cia']);
        $this->assertDatabaseHas('worship_service_assignments', ['assignee_name' => 'Ryan']);
        $this->assertDatabaseHas('worship_service_assignments', ['assignee_name' => 'Zerren']);

        $builder->saveRecord([
            'month' => '2026-06',
            'update_note' => 'Catatan final',
            'rows' => [
                ['role' => 'LW', 'assignments' => ['Budi', '', '', '']],
                ['role' => 'Jadwal Latihan', 'assignments' => ['', '', '', '']],
            ],
        ]);

        $this->assertDatabaseCount('worship_service_schedules', 1);
        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'update_note' => 'Catatan final',
        ]);
        $this->assertDatabaseMissing('worship_service_assignments', ['assignee_name' => 'Cia']);
        $this->assertDatabaseHas('worship_service_assignments', ['assignee_name' => 'Budi']);

        $this->assertTrue($builder->deleteMonth('2026-06'));
        $this->assertDatabaseCount('worship_service_schedules', 0);
        $this->assertDatabaseCount('worship_service_schedule_roles', 0);
        $this->assertDatabaseCount('worship_service_schedule_weeks', 0);
        $this->assertDatabaseCount('worship_service_assignments', 0);
    }

    public function test_worship_page_uses_the_shared_page_header(): void
    {
        $this->createWorshipServiceScheduleTables();
        $this->actingAsRecUser('keziaae', null, 'pelayan');

        $this->get('/ibadah/penatalayan?month=2026-06')
            ->assertOk()
            ->assertSee('data-worship-header', false)
            ->assertSee('discipleship-page-header__stats', false)
            ->assertSee('discipleship-page-header__tools', false)
            ->assertSee('Penatalayan Ibadah Umum')
            ->assertSee('Catatan Update')
            ->assertDontSee('Judul Jadwal');
    }

    private function createWorshipServiceScheduleTables(): void
    {
        Schema::dropIfExists('worship_service_assignments');
        Schema::dropIfExists('worship_service_schedule_weeks');
        Schema::dropIfExists('worship_service_schedule_roles');
        Schema::dropIfExists('worship_service_schedules');
        Schema::dropIfExists('worship_schedules');

        Schema::create('worship_service_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->unique();
            $table->longText('update_note')->nullable();
            $table->timestamps();
        });

        Schema::create('worship_service_schedule_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('worship_service_schedule_id')->constrained('worship_service_schedules')->cascadeOnDelete();
            $table->string('role_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('worship_service_schedule_weeks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('worship_service_schedule_id')->constrained('worship_service_schedules')->cascadeOnDelete();
            $table->unsignedTinyInteger('week_index');
            $table->date('service_date');
            $table->date('training_date')->nullable();
            $table->timestamps();
        });

        Schema::create('worship_service_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('worship_service_schedule_role_id')->constrained('worship_service_schedule_roles')->cascadeOnDelete();
            $table->foreignId('worship_service_schedule_week_id')->constrained('worship_service_schedule_weeks')->cascadeOnDelete();
            $table->string('assignee_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
}
