<?php

namespace Tests\Feature;

use App\Services\Branches\BranchCatalog;
use App\Services\DiscipleshipDashboard\DiscipleshipDashboardSummaryQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\AssertsDiscipleshipWorkspace;
use Tests\TestCase;

class DiscipleshipDashboardTest extends TestCase
{
    use AssertsDiscipleshipWorkspace;

    public function test_dashboard_renders_from_laravel_tables(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Dashboard Pemuridan');
        $response->assertSee('data-discipleship-dashboard-header', false);
        $response->assertSee('card discipleship-page-header discipleship-dashboard-hero-card', false);
        $response->assertSee('discipleship-dashboard-hero-summary-ring', false);
        $response->assertSee('style="--pct:', false);
        $response->assertSee('Buka Halaman Public Link');
        $response->assertSee('Belum Selesai MSK');
        $response->assertDontSee('Kelompok Berjalan');
        $response->assertDontSee('Kelompok aktif');
        $response->assertSee('discipleship-dashboard-group-progress', false);
        $this->assertLessThan(
            strpos((string) $response->getContent(), 'discipleship-dashboard-group-progress'),
            strpos((string) $response->getContent(), 'discipleship-dashboard-data-stats'),
        );
        $response->assertDontSee('Peserta MSK Dashboard');
        $response->assertDontSee('?page=discipleship_dashboard', false);
        $this->assertDiscipleshipWorkspace((string) $response->getContent(), 'dashboard');
        $this->assertUnifiedDiscipleshipSidebar((string) $response->getContent(), 'Kutisari');

        $section = $this->get('/pemuridan/dashboard/sections/incomplete-msk');
        $section->assertOk();
        $section->assertSee('Peserta MSK Dashboard');
        $section->assertSee('name="_token"', false);
        $section->assertSee('href="https://wa.me/62833333333"', false);
        $section->assertSee('data-msk-edit-open', false);
        $section->assertDontSee('dashboard-section-pagination', false);
        $response->assertSee('data-msk-edit-modal', false);

        $overdueSection = $this->get('/pemuridan/dashboard/sections/overdue-groups');
        $overdueSection->assertOk();
        $overdueSection->assertSee('<span>Peserta</span>', false);
        $overdueSection->assertDontSee('<span>Kelompok</span>', false);
        $overdueSection->assertDontSee('<strong>Kelompok Dashboard</strong>', false);
        $overdueSection->assertDontSee('dashboard-section-pagination', false);
    }

    public function test_dashboard_tab_fragment_returns_only_the_marked_panel(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();
        $this->actingAsRecUser();

        $response = $this->withHeaders([
            'X-Discipleship-Fragment' => 'tab',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get('/pemuridan/dashboard')->assertOk();

        $response->assertSee('Dashboard Pemuridan');
        $response->assertSee('data-discipleship-dashboard-header', false);
        $response->assertDontSee('<script', false);
        $this->assertDiscipleshipTabFragment((string) $response->getContent(), 'dashboard');
    }

    public function test_dashboard_updates_msk_sessions_to_laravel_tables(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();

        $this->actingAsRecUser();
        $participantId = (int) DB::table('orang')->where('full_name', 'Peserta MSK Dashboard')->value('id');

        $response = $this->post('/pemuridan/dashboard/msk-sessions', [
            'action' => 'save_msk_sessions',
            'id' => $participantId,
            'return_page' => 'discipleship_dashboard',
            'session_numbers' => ['1', '2', '3', '4'],
        ]);

        $response->assertRedirect('/pemuridan/dashboard?msk_session_saved=1');

        $sessions = json_decode((string) DB::table('orang')->where('id', $participantId)->value('session_numbers'), true);
        $this->assertSame([1, 2, 3, 4], $sessions);
    }

    public function test_central_discipleship_user_cannot_update_branch_data(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();
        $this->actingAsRecUser('recpusat', null, 'pemuridan_pusat');
        $participantId = (int) DB::table('orang')->where('full_name', 'Peserta MSK Dashboard')->value('id');

        $this->post('/pemuridan/dashboard/msk-sessions', [
            'id' => $participantId,
            'session_numbers' => ['1', '2', '3', '4'],
        ])->assertRedirect('/pemuridan/dashboard?error=access_denied');

        $sessions = json_decode((string) DB::table('orang')->where('id', $participantId)->value('session_numbers'), true);
        $this->assertSame([1, 2], $sessions);

        $this->get('/pemuridan/dashboard/sections/incomplete-msk')
            ->assertOk()
            ->assertSee('Peserta MSK Dashboard')
            ->assertDontSee('data-msk-edit-open', false);
    }

    public function test_developer_testing_branch_can_update_dashboard_msk_sessions(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();
        $testingBranchId = $this->seedTestingBranch();
        $testingParticipantId = DB::table('orang')->insertGetId([
            'branch_id' => $testingBranchId,
            'full_name' => 'Peserta Testing Dashboard',
            'gender' => 'Perempuan',
            'whatsapp' => '0844444444',
            'batch_month' => '2026-07',
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'session_numbers' => json_encode([1]),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAsRecUser('developer', null, 'developer');

        $this->get('/pemuridan/dashboard?branch_id='.$testingBranchId)
            ->assertOk()
            ->assertSee('is-developer-experiment-branch', false)
            ->assertSee('data-discipleship-branch-filter', false)
            ->assertSee('<option value="'.$testingBranchId.'" selected>Testing</option>', false)
            ->assertDontSee('central-rekap-toolbar', false)
            ->assertDontSee('Peserta MSK Dashboard');
        $this->get('/pemuridan/dashboard/sections/incomplete-msk')
            ->assertOk()
            ->assertSee('Peserta Testing Dashboard')
            ->assertDontSee('Peserta MSK Dashboard');

        $this->post('/pemuridan/dashboard/msk-sessions', [
            'action' => 'save_msk_sessions',
            'id' => $testingParticipantId,
            'return_page' => 'discipleship_dashboard',
            'session_numbers' => ['1', '2', '3'],
        ])->assertRedirect('/pemuridan/dashboard?msk_session_saved=1');

        $sessions = json_decode((string) DB::table('orang')->where('id', $testingParticipantId)->value('session_numbers'), true);
        $this->assertSame([1, 2, 3], $sessions);
    }

    public function test_dashboard_initial_response_uses_aggregate_queries_and_bounded_html(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();
        $this->actingAsRecUser();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get('/pemuridan/dashboard');
        $queries = DB::getQueryLog();

        $response->assertOk();
        $this->assertLessThanOrEqual(13, count($queries));
        $this->assertLessThan(100 * 1024, strlen((string) $response->getContent()));
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('select *', strtolower((string) $query['query']));
        }
    }

    public function test_dashboard_summary_preserves_historical_achievements_and_excludes_archived_participant_identities(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();

        $memberId = (int) DB::table('orang')->where('full_name', 'Anggota Dashboard')->value('id');
        $groupId = (int) DB::table('kelompok_dg')->orderBy('id')->value('id');
        $additionalLeaderId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Leader Tambahan Dashboard',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $formerLeaderId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Mantan Leader Dashboard',
            'status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $formerMemberId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Mantan Peserta Dashboard',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $archivedMemberId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Identitas Peserta Diarsipkan',
            'status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orang')->insert([
            'branch_id' => 1,
            'full_name' => 'Alumni MSK Dashboard',
            'journey_bridge_status' => 'sudah_kgap',
            'status' => 'inactive',
            'session_numbers' => json_encode(range(1, 12)),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $memberId,
            'role' => 'member',
            'stage' => 'DG 1',
            'status' => 'completed',
            'started_on' => '2026-01-01',
            'ended_on' => '2026-05-01',
            'end_reason' => 'stage_transition',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $formerLeaderId,
            'role' => 'leader',
            'status' => 'completed',
            'started_on' => '2025-01-01',
            'ended_on' => '2025-12-31',
            'end_reason' => 'group_completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $additionalLeaderId,
            'role' => 'leader',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $formerMemberId,
            'role' => 'member',
            'stage' => 'DG 1',
            'status' => 'completed',
            'started_on' => '2025-01-01',
            'ended_on' => '2025-12-31',
            'end_reason' => 'group_completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $archivedMemberId,
            'role' => 'member',
            'stage' => 'DG 1',
            'status' => 'closed',
            'started_on' => '2025-01-01',
            'ended_on' => '2025-12-31',
            'end_reason' => 'person_archived',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsRecUser();
        $summary = app(DiscipleshipDashboardSummaryQuery::class)->get();
        $journey = collect($summary['journeyProgressRows'])->keyBy('label');
        $stats = collect($summary['summaryStats'])->keyBy('label');

        $this->assertSame(1, $journey['Selesai MSK']['value']);
        $this->assertSame(2, $journey['Selesai DG 1']['value']);
        $this->assertSame(1, $journey['Selesai Kamp GAP']['value']);
        $this->assertSame(2, $stats['Peserta Selama Ini']['value']);
        $this->assertSame(3, $stats['Pernah Memimpin']['value']);
        $this->assertSame(1, $stats['Belum Selesai MSK']['value']);
    }

    public function test_dashboard_counts_continued_groups_as_one_historical_group(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();
        $firstGroupId = (int) DB::table('kelompok_dg')->orderBy('id')->value('id');
        DB::table('kelompok_dg')->where('id', $firstGroupId)->update(['status' => 'completed']);

        $secondGroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'completed',
            'stage' => 'DG 2',
            'parent_group_id' => $firstGroupId,
            'source_group_id' => $firstGroupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $thirdGroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 3',
            'parent_group_id' => $secondGroupId,
            'source_group_id' => $secondGroupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $independentGroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $leaderId = (int) DB::table('orang')->where('full_name', 'Leader Dashboard')->value('id');
        $memberId = (int) DB::table('orang')->where('full_name', 'Anggota Dashboard')->value('id');
        foreach ([$secondGroupId, $thirdGroupId, $independentGroupId] as $groupId) {
            DB::table('keanggotaan_kelompok_dg')->insert([
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => $groupId === $independentGroupId ? $leaderId : $memberId,
                'role' => $groupId === $independentGroupId ? 'leader' : 'member',
                'stage' => $groupId === $thirdGroupId ? 'DG 3' : 'DG 2',
                'status' => $groupId === $secondGroupId ? 'closed' : 'active',
                'ended_on' => $groupId === $secondGroupId ? '2026-05-01' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAsRecUser();
        $summary = app(DiscipleshipDashboardSummaryQuery::class)->get();
        $stats = collect($summary['summaryStats'])->keyBy('label');
        $progress = collect($summary['groupProgressRows'])->keyBy('label');

        $this->assertSame(2, $stats['Kelompok Selama Ini']['value']);
        $this->assertSame('Kelompok yang pernah berjalan', $stats['Kelompok Selama Ini']['sub']);
        $this->assertSame(2, $progress['DG 1 Berjalan']['target']);
        $this->assertSame(1, $progress['DG 1 Berjalan']['value']);
        $this->assertSame(1, $progress['DG 3 Berjalan']['value']);
    }

    public function test_incomplete_msk_section_renders_all_rows_without_pagination(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();
        $rows = [];
        for ($index = 1; $index <= 60; $index++) {
            $rows[] = [
                'branch_id' => 1,
                'full_name' => sprintf('Peserta Batas %03d', $index),
                'status' => 'active',
                'session_numbers' => json_encode([1]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('orang')->insert($rows);
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/dashboard/sections/incomplete-msk?per_page=500');

        $response->assertOk()
            ->assertDontSee('Halaman 1 dari')
            ->assertDontSee('dashboard-section-pagination', false);
        $this->assertSame(61, substr_count((string) $response->getContent(), 'data-msk-edit-open='));
    }

    public function test_overdue_groups_section_renders_all_rows_without_pagination(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();
        $groups = [];
        for ($index = 1; $index <= 25; $index++) {
            $groups[] = [
                'branch_id' => 1,
                'status' => 'active',
                'stage' => 'DG 1',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('kelompok_dg')->insert($groups);
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/dashboard/sections/overdue-groups?per_page=1');

        $response->assertOk()
            ->assertSee('<span class="discipleship-overdue-count">26</span>', false)
            ->assertDontSee('Halaman 1 dari')
            ->assertDontSee('dashboard-section-pagination', false);
        $this->assertSame(26, substr_count((string) $response->getContent(), 'class="discipleship-overdue-item"'));
    }

    private function createDashboardTables(): void
    {
        Schema::dropIfExists('jurnal_temu_dg');
        Schema::dropIfExists('dg_manual');
        Schema::dropIfExists('keanggotaan_kelompok_dg');
        Schema::dropIfExists('relasi_dg');
        Schema::dropIfExists('kelompok_dg');
        Schema::dropIfExists('orang');
        Schema::dropIfExists('cabang');

        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('camp_gap_participant_target')->default(50);
            $table->unsignedInteger('msk_completion_target')->default(50);
            $table->unsignedInteger('dg1_completion_target')->default(50);
            $table->unsignedInteger('dg2_completion_target')->default(50);
            $table->unsignedInteger('dg3_completion_target')->default(50);
            $table->timestamps();
        });

        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('batch_month')->nullable();
            $table->text('notes')->nullable();
            $table->string('completed_at')->nullable();
            $table->string('journey_bridge_status')->default('belum');
            $table->string('status')->default('active');
            $table->json('session_numbers')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });
        app(BranchCatalog::class)->clearCache();

        Schema::create('kelompok_dg', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('status')->default('active');
            $table->string('stage')->nullable();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->date('multiplied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('keanggotaan_kelompok_dg', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('role')->default('member');
            $table->string('stage')->nullable();
            $table->string('status')->default('active');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('dg_manual', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('person_id');
            $table->string('stage');
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('jurnal_temu_dg', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->string('leader_name_snapshot')->nullable();
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->date('meeting_date');
            $table->string('material_topic')->nullable();
            $table->string('group_progress_snapshot')->nullable();
            $table->text('absence_reason')->nullable();
            $table->json('absences')->nullable();
            $table->json('meditation_sharers')->nullable();
            $table->json('photos')->nullable();
            $table->text('additional_notes')->nullable();
            $table->unsignedInteger('meditation_min_times')->default(0);
            $table->unsignedInteger('sharing_openness_score')->nullable();
            $table->boolean('prepared_material')->default(false);
            $table->boolean('prayed_for_members')->default(false);
            $table->boolean('shared_meditation')->default(false);
            $table->boolean('relationally_contacted')->default(false);
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    private function seedDashboardData(): void
    {
        DB::table('cabang')->insert([
            'id' => 1,
            'label' => 'Kutisari',
            'is_active' => true,
            'camp_gap_participant_target' => 10,
            'msk_completion_target' => 10,
            'dg1_completion_target' => 10,
            'dg2_completion_target' => 10,
            'dg3_completion_target' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(BranchCatalog::class)->clearCache();

        $leaderId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Leader Dashboard',
            'whatsapp' => '0811111111',
            'gender' => 'Laki-laki',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Dashboard',
            'whatsapp' => '0822222222',
            'gender' => 'Perempuan',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $leaderId,
            'role' => 'leader',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $memberId,
            'role' => 'member',
            'stage' => 'DG 1',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $participantId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Peserta MSK Dashboard',
            'gender' => 'Laki-laki',
            'whatsapp' => '0833333333',
            'batch_month' => '2026-06',
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'session_numbers' => json_encode([1, 2]),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedTestingBranch(): int
    {
        $id = (int) DB::table('cabang')->insertGetId([
            'label' => 'Testing',
            'is_active' => false,
            'camp_gap_participant_target' => 50,
            'msk_completion_target' => 50,
            'dg1_completion_target' => 50,
            'dg2_completion_target' => 50,
            'dg3_completion_target' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(BranchCatalog::class)->clearCache();

        return $id;
    }
}

