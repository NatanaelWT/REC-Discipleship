<?php

namespace Tests\Feature;

use App\Services\DiscipleshipDashboard\DiscipleshipDashboardSummaryQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscipleshipDashboardTest extends TestCase
{
    public function test_dashboard_renders_from_laravel_tables(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Dashboard Pemuridan');
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

        $section = $this->get('/pemuridan/dashboard/sections/incomplete-msk');
        $section->assertOk();
        $section->assertSee('Peserta MSK Dashboard');
        $section->assertSee('name="_token"', false);
        $section->assertSee('href="https://wa.me/62833333333"', false);
        $section->assertSee('data-msk-edit-open', false);
        $response->assertSee("modal.classList.add('is-open');", false);
        $response->assertDontSee("modal.classList.add('open');", false);

        $overdueSection = $this->get('/pemuridan/dashboard/sections/overdue-groups');
        $overdueSection->assertOk();
        $overdueSection->assertSee('<span>Peserta</span>', false);
        $overdueSection->assertDontSee('<span>Kelompok</span>', false);
        $overdueSection->assertDontSee('<strong>Kelompok Dashboard</strong>', false);
    }

    public function test_dashboard_updates_msk_sessions_to_laravel_tables(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();

        $this->actingAsRecUser();
        $participantId = (int) DB::table('msk_participants')->where('full_name', 'Peserta MSK Dashboard')->value('id');

        $response = $this->post('/pemuridan/dashboard/msk-sessions', [
            'action' => 'save_msk_sessions',
            'id' => $participantId,
            'return_page' => 'discipleship_dashboard',
            'session_numbers' => ['1', '2', '3', '4'],
        ]);

        $response->assertRedirect('/pemuridan/dashboard?msk_session_saved=1');

        $sessions = json_decode((string) DB::table('msk_participants')->where('id', $participantId)->value('session_numbers'), true);
        $this->assertSame([1, 2, 3, 4], $sessions);
    }

    public function test_central_discipleship_user_cannot_update_branch_data(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();
        $this->actingAsRecUser('recpusat', null, 'pemuridan_pusat');
        $participantId = (int) DB::table('msk_participants')->where('full_name', 'Peserta MSK Dashboard')->value('id');

        $this->post('/pemuridan/dashboard/msk-sessions', [
            'id' => $participantId,
            'session_numbers' => ['1', '2', '3', '4'],
        ])->assertRedirect('/pemuridan/dashboard?error=access_denied');

        $sessions = json_decode((string) DB::table('msk_participants')->where('id', $participantId)->value('session_numbers'), true);
        $this->assertSame([1, 2], $sessions);

        $this->get('/pemuridan/dashboard/sections/incomplete-msk')
            ->assertOk()
            ->assertSee('Peserta MSK Dashboard')
            ->assertDontSee('data-msk-edit-open', false);
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
        $this->assertLessThanOrEqual(11, count($queries));
        $this->assertLessThan(100 * 1024, strlen((string) $response->getContent()));
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('select *', strtolower((string) $query['query']));
        }
    }

    public function test_dashboard_summary_preserves_historical_achievements_and_mentor_leaders(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();

        $memberId = (int) DB::table('discipleship_people')->where('full_name', 'Anggota Dashboard')->value('id');
        $groupId = (int) DB::table('discipleship_groups')->where('name', 'Kelompok Dashboard')->value('id');
        $mentorId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Mentor Tanpa Kelompok',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $formerLeaderId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Mantan Leader Dashboard',
            'status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $formerMemberId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Mantan Peserta Dashboard',
            'status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('msk_participants')->insert([
            'branch_id' => 1,
            'discipleship_person_id' => $memberId,
            'full_name' => 'Alumni MSK Dashboard',
            'journey_bridge_status' => 'sudah_kgap',
            'status' => 'inactive',
            'session_numbers' => json_encode(range(1, 12)),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_group_people')->insert([
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
        DB::table('discipleship_group_people')->insert([
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
        DB::table('discipleship_group_people')->insert([
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
        DB::table('discipleship_relationships')->insert([
            'branch_id' => 1,
            'mentor_person_id' => $mentorId,
            'disciple_person_id' => $memberId,
            'context_group_id' => $groupId,
            'relation_type' => 'discipleship',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsRecUser();
        $summary = app(DiscipleshipDashboardSummaryQuery::class)->get();
        $journey = collect($summary['journeyProgressRows'])->keyBy('label');
        $stats = collect($summary['summaryStats'])->keyBy('label');

        $this->assertSame(1, $journey['Selesai MSK']['value']);
        $this->assertSame(1, $journey['Selesai DG 1']['value']);
        $this->assertSame(1, $journey['Selesai Kamp GAP']['value']);
        $this->assertSame(2, $stats['Peserta Selama Ini']['value']);
        $this->assertSame(3, $stats['Pernah Memimpin']['value']);
        $this->assertSame(1, $stats['Belum Selesai MSK']['value']);
    }

    public function test_dashboard_counts_continued_groups_as_one_historical_group(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();
        $firstGroupId = (int) DB::table('discipleship_groups')->where('name', 'Kelompok Dashboard')->value('id');
        DB::table('discipleship_groups')->where('id', $firstGroupId)->update(['status' => 'completed']);

        $secondGroupId = DB::table('discipleship_groups')->insertGetId([
            'branch_id' => 1,
            'name' => 'Kelompok Dashboard DG 2',
            'status' => 'completed',
            'start_stage' => 'DG 2',
            'current_stage' => 'DG 2',
            'parent_group_id' => $firstGroupId,
            'source_group_id' => $firstGroupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $thirdGroupId = DB::table('discipleship_groups')->insertGetId([
            'branch_id' => 1,
            'name' => 'Kelompok Dashboard DG 3',
            'status' => 'active',
            'start_stage' => 'DG 3',
            'current_stage' => 'DG 3',
            'parent_group_id' => $secondGroupId,
            'source_group_id' => $secondGroupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $independentGroupId = DB::table('discipleship_groups')->insertGetId([
            'branch_id' => 1,
            'name' => 'Kelompok Independen',
            'status' => 'active',
            'start_stage' => 'DG 1',
            'current_stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $leaderId = (int) DB::table('discipleship_people')->where('full_name', 'Leader Dashboard')->value('id');
        $memberId = (int) DB::table('discipleship_people')->where('full_name', 'Anggota Dashboard')->value('id');
        foreach ([$secondGroupId, $thirdGroupId, $independentGroupId] as $groupId) {
            DB::table('discipleship_group_people')->insert([
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

    public function test_incomplete_msk_section_caps_page_size_at_fifty(): void
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
        DB::table('msk_participants')->insert($rows);
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/dashboard/sections/incomplete-msk?per_page=500');

        $response->assertOk()->assertSee('Halaman 1 dari 2');
        $this->assertSame(50, substr_count((string) $response->getContent(), 'data-msk-edit-open='));
    }

    private function createDashboardTables(): void
    {
        Schema::dropIfExists('msk_participants');
        Schema::dropIfExists('discipleship_meeting_reports');
        Schema::dropIfExists('discipleship_group_people');
        Schema::dropIfExists('discipleship_relationships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');
        Schema::dropIfExists('branches');

        Schema::create('branches', function (Blueprint $table): void {
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

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->string('campus')->nullable();
            $table->string('major')->nullable();
            $table->string('occupation')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('start_stage')->nullable();
            $table->string('current_stage')->nullable();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->date('multiplied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->unsignedBigInteger('context_group_id')->nullable();
            $table->string('relation_type')->nullable();
            $table->string('stage_at_start')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_people', function (Blueprint $table): void {
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

        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_person_id')->nullable()->unique();
            $table->string('full_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_day_month')->nullable();
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

        Schema::create('discipleship_meeting_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->string('leader_name_snapshot')->nullable();
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->string('group_name_snapshot')->nullable();
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
        DB::table('branches')->insert([
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

        $leaderId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Leader Dashboard',
            'phone' => '0811111111',
            'gender' => 'Laki-laki',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Dashboard',
            'phone' => '0822222222',
            'gender' => 'Perempuan',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('discipleship_groups')->insertGetId([
            'branch_id' => 1,
            'name' => 'Kelompok Dashboard',
            'status' => 'active',
            'start_stage' => 'DG 1',
            'current_stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_group_people')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $leaderId,
            'role' => 'leader',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_group_people')->insert([
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

        DB::table('discipleship_relationships')->insert([
            'branch_id' => 1,
            'mentor_person_id' => $leaderId,
            'disciple_person_id' => $memberId,
            'context_group_id' => $groupId,
            'relation_type' => 'discipleship',
            'stage_at_start' => 'DG 1',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $participantId = DB::table('msk_participants')->insertGetId([
            'branch_id' => 1,
            'discipleship_person_id' => null,
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
}
