<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DgMeetingReportRecapTest extends TestCase
{
    public function test_legacy_dg_report_recap_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/laporan-dg?page=dg_reports_recap');

        $response->assertNotFound();
    }

    public function test_dg_report_recap_renders_from_laravel_tables(): void
    {
        $this->createTables();
        $this->seedReport();

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/laporan-dg');

        $response->assertStatus(200);
        $response->assertSee('Rekap Laporan DG');
        $response->assertSee('Pemimpin Test');
        $response->assertSee('Materi Test');
    }

    public function test_central_discipleship_user_can_view_and_filter_all_branches(): void
    {
        $this->createTables();
        $this->seedReport();
        $this->actingAsRecUser('recpusat', null, 'pemuridan_pusat');

        $this->get('/pemuridan/laporan-dg?rekap_cabang=all')
            ->assertOk()
            ->assertSee('Materi Test')
            ->assertSee('Mode Pusat')
            ->assertSee('Semua Cabang');

        $this->get('/pemuridan/laporan-dg?rekap_cabang=gm')
            ->assertOk()
            ->assertDontSee('Materi Test');
    }

    public function test_developer_views_discipleship_recap_as_readonly_all_branch_preview(): void
    {
        $this->createTables();
        $this->seedReport();
        $this->actingAsRecUser('developer', null, 'developer');

        $this->assertSame('', current_user_branch());
        $this->assertTrue(is_effective_central_discipleship_readonly());

        $this->get('/pemuridan/laporan-dg?rekap_cabang=all')
            ->assertOk()
            ->assertSee('Materi Test');
    }

    public function test_public_dg_form_normalizes_numeric_ids_for_javascript_selects(): void
    {
        $this->createTables();
        $this->seedReport();

        $this->get('/publik/jurnal-dg/kutisari/laporan')
            ->assertOk()
            ->assertSee('Pemimpin Test')
            ->assertSee("groupRow.id = String(groupRow.id || '');", false)
            ->assertSee("groupRow.leader_id = String(groupRow.leader_id || '');", false)
            ->assertSee("memberRow.id = String(memberRow.id || '');", false);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('discipleship_meeting_reports');
        Schema::dropIfExists('discipleship_group_people');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');

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

        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('role')->default('leader');
            $table->string('stage')->nullable();
            $table->string('status')->default('active');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_meeting_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->string('leader_name_snapshot')->nullable();
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->string('group_name_snapshot')->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('material_topic')->nullable();
            $table->string('group_progress_snapshot')->nullable();
            $table->text('absence_reason')->nullable();
            $table->json('absences')->nullable();
            $table->json('meditation_sharers')->nullable();
            $table->json('photos')->nullable();
            $table->longText('additional_notes')->nullable();
            $table->unsignedTinyInteger('meditation_min_times')->default(0);
            $table->unsignedTinyInteger('sharing_openness_score')->nullable();
            $table->boolean('prepared_material')->default(false);
            $table->boolean('prayed_for_members')->default(false);
            $table->boolean('shared_meditation')->default(false);
            $table->boolean('relationally_contacted')->default(false);
            $table->string('source')->default('public_form');
            $table->timestamps();
        });

    }

    private function seedReport(): void
    {
        $leaderId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Pemimpin Test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('discipleship_groups')->insertGetId([
            'branch_id' => 1,
            'name' => 'Kelompok Test',
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

        $reportId = DB::table('discipleship_meeting_reports')->insertGetId([
            'branch_id' => 1,
            'leader_person_id' => $leaderId,
            'leader_name_snapshot' => 'Pemimpin Test',
            'discipleship_group_id' => $groupId,
            'group_name_snapshot' => 'Kelompok Test',
            'meeting_date' => '2026-06-01',
            'material_topic' => 'Materi Test',
            'group_progress_snapshot' => 'DG 1',
            'absence_reason' => 'Sakit',
            'absences' => json_encode([[
                'person_id' => $memberId,
                'person_name_snapshot' => 'Anggota Test',
            ]]),
            'meditation_sharers' => json_encode([]),
            'photos' => json_encode([]),
            'additional_notes' => 'Catatan laporan',
            'meditation_min_times' => 2,
            'sharing_openness_score' => 8,
            'prepared_material' => true,
            'prayed_for_members' => true,
            'shared_meditation' => false,
            'relationally_contacted' => true,
            'source' => 'public_form',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }
}
