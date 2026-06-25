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
        $this->createWorshipScheduleTable();
        DB::table('worship_schedules')->insert([
            'month' => '2026-06',
            'update_note' => null,
            'rows' => '[]',
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

        $this->assertDatabaseCount('worship_schedules', 1);
        $this->assertDatabaseHas('worship_schedules', [
            'month' => '2026-06',
            'update_note' => 'Diperbarui developer',
        ]);
    }

    public function test_worship_page_uses_the_shared_page_header(): void
    {
        $this->createWorshipScheduleTable();
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

    private function createWorshipScheduleTable(): void
    {
        Schema::dropIfExists('worship_schedules');
        Schema::create('worship_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->unique();
            $table->string('update_note')->nullable();
            $table->json('rows')->nullable();
            $table->timestamps();
        });
    }
}
