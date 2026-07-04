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
            'row_type' => 'training',
            'role_name' => 'Jadwal Latihan',
            'role_sort_order' => 0,
            'week_index' => 0,
            'service_date' => '2026-06-07',
            'training_date' => null,
            'assignee_name' => null,
            'assignee_sort_order' => 0,
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
        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'update_note' => 'Diperbarui developer',
        ]);
        $this->assertGreaterThan(
            1,
            DB::table('worship_service_schedules')->where('month', '2026-06')->count(),
        );
    }

    public function test_worship_schedule_save_replace_and_delete_use_flat_rows(): void
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

        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'update_note' => 'Catatan awal',
            'row_type' => 'assignment',
            'role_name' => 'LW',
            'week_index' => 0,
            'service_date' => '2026-06-07',
            'assignee_name' => 'Cia',
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'row_type' => 'assignment',
            'role_name' => 'Singer',
            'week_index' => 0,
            'assignee_name' => 'Ryan',
            'assignee_sort_order' => 0,
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'row_type' => 'assignment',
            'role_name' => 'Singer',
            'week_index' => 0,
            'assignee_name' => 'Zerren',
            'assignee_sort_order' => 1,
        ]);
        $this->assertDatabaseHas('worship_service_schedules', [
            'row_type' => 'training',
            'role_name' => 'Jadwal Latihan',
            'week_index' => 0,
            'service_date' => '2026-06-07',
            'training_date' => '2026-06-05',
            'assignee_name' => null,
        ]);
        $this->assertFalse(Schema::hasTable('worship_service_schedule_roles'));
        $this->assertFalse(Schema::hasTable('worship_service_schedule_weeks'));
        $this->assertFalse(Schema::hasTable('worship_service_assignments'));

        $builder->saveRecord([
            'month' => '2026-06',
            'update_note' => 'Catatan final',
            'rows' => [
                ['role' => 'LW', 'assignments' => ['Budi', '', '', '']],
                ['role' => 'Jadwal Latihan', 'assignments' => ['', '', '', '']],
            ],
        ]);

        $this->assertDatabaseHas('worship_service_schedules', [
            'month' => '2026-06',
            'update_note' => 'Catatan final',
            'role_name' => 'LW',
            'assignee_name' => 'Budi',
        ]);
        $this->assertDatabaseMissing('worship_service_schedules', ['assignee_name' => 'Cia']);
        $this->assertDatabaseMissing('worship_service_schedules', ['assignee_name' => 'Ryan']);

        $this->assertTrue($builder->deleteMonth('2026-06'));
        $this->assertDatabaseCount('worship_service_schedules', 0);
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

    public function test_worship_schedule_image_export_uses_flat_rows(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for worship schedule PNG export.');
        }

        $this->createWorshipServiceScheduleTables();
        app(WorshipServiceScheduleBuilder::class)->saveRecord([
            'month' => '2026-06',
            'update_note' => 'Untuk export',
            'rows' => [
                ['role' => 'LW', 'assignments' => ['Cia', '', '', '']],
                ['role' => 'Singer', 'assignments' => ["Ryan\nZerren", '', '', '']],
                ['role' => 'Jadwal Latihan', 'assignments' => ['2026-06-05', '', '', '']],
            ],
        ]);
        $this->actingAsRecUser('keziaae', null, 'pelayan');

        $response = $this->get('/ibadah/penatalayan/gambar?month=2026-06')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertStringStartsWith("\x89PNG\r\n\x1A\n", $response->getContent());
    }

    private function createWorshipServiceScheduleTables(): void
    {
        Schema::dropIfExists('worship_service_schedules_flat');
        Schema::dropIfExists('worship_service_assignments');
        Schema::dropIfExists('worship_service_schedule_weeks');
        Schema::dropIfExists('worship_service_schedule_roles');
        Schema::dropIfExists('worship_service_schedules');
        Schema::dropIfExists('worship_schedules');

        Schema::create('worship_service_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->index();
            $table->longText('update_note')->nullable();
            $table->string('row_type', 20)->default('assignment')->index();
            $table->string('role_name');
            $table->unsignedSmallInteger('role_sort_order')->default(0);
            $table->unsignedTinyInteger('week_index')->default(0);
            $table->date('service_date');
            $table->date('training_date')->nullable();
            $table->string('assignee_name')->nullable();
            $table->unsignedSmallInteger('assignee_sort_order')->default(0);
            $table->timestamps();
        });
    }
}
