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

        $previousSession = $this->signInAsBranchUser();

        $response = $this->get('/pemuridan/laporan-dg');

        $response->assertStatus(200);
        $response->assertSee('Rekap Laporan DG');
        $response->assertSee('Pemimpin Test');
        $response->assertSee('Materi Test');

        $this->restoreSession($previousSession);
    }

    /**
     * @return array<string, mixed>
     */
    private function signInAsBranchUser(): array
    {
        $previousSession = $_SESSION ?? [];
        if (session_status() === PHP_SESSION_NONE) {
            session_save_path(storage_path('framework/sessions'));
            session_id('dg-report-recap-test-'.str_replace('.', '', uniqid('', true)));
            session_start();
        }
        $_SESSION['user'] = 'tester';
        $_SESSION['cabang'] = 'kutisari';
        $_SESSION['access_scope'] = 'branch';

        return $previousSession;
    }

    /**
     * @param  array<string, mixed>  $previousSession
     */
    private function restoreSession(array $previousSession): void
    {
        $_SESSION = $previousSession;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function createTables(): void
    {
        Schema::dropIfExists('discipleship_meeting_report_photos');
        Schema::dropIfExists('discipleship_meeting_report_meditation_sharers');
        Schema::dropIfExists('discipleship_meeting_report_absences');
        Schema::dropIfExists('discipleship_meeting_reports');
        Schema::dropIfExists('discipleship_group_leaderships');
        Schema::dropIfExists('discipleship_group_memberships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->string('branch_code', 40);
            $table->string('member_public_id')->nullable();
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
            $table->string('public_id');
            $table->string('branch_code', 40);
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('start_stage')->nullable();
            $table->string('current_stage')->nullable();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->string('parent_group_public_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_leaderships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('discipleship_group_id');
            $table->string('group_public_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_public_id')->nullable();
            $table->string('role')->default('leader');
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_change')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('discipleship_group_id');
            $table->string('group_public_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_public_id')->nullable();
            $table->string('role')->default('member');
            $table->string('stage')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_meeting_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->string('leader_person_public_id')->nullable();
            $table->string('leader_name_snapshot')->nullable();
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->string('discipleship_group_public_id')->nullable();
            $table->string('group_name_snapshot')->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('material_topic')->nullable();
            $table->string('group_progress_snapshot')->nullable();
            $table->text('absence_reason')->nullable();
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

        Schema::create('discipleship_meeting_report_absences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discipleship_meeting_report_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_public_id')->nullable();
            $table->string('person_name_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_meeting_report_meditation_sharers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discipleship_meeting_report_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_public_id')->nullable();
            $table->string('person_name_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_meeting_report_photos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discipleship_meeting_report_id');
            $table->string('relative_path');
            $table->string('original_file_name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    private function seedReport(): void
    {
        $leaderId = DB::table('discipleship_people')->insertGetId([
            'public_id' => 'person-leader',
            'branch_code' => 'kutisari',
            'full_name' => 'Pemimpin Test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('discipleship_people')->insertGetId([
            'public_id' => 'person-member',
            'branch_code' => 'kutisari',
            'full_name' => 'Anggota Test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('discipleship_groups')->insertGetId([
            'public_id' => 'group-test',
            'branch_code' => 'kutisari',
            'name' => 'Kelompok Test',
            'status' => 'active',
            'start_stage' => 'DG 1',
            'current_stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_group_leaderships')->insert([
            'public_id' => 'leadership-test',
            'branch_code' => 'kutisari',
            'discipleship_group_id' => $groupId,
            'group_public_id' => 'group-test',
            'person_id' => $leaderId,
            'person_public_id' => 'person-leader',
            'role' => 'leader',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_group_memberships')->insert([
            'public_id' => 'membership-test',
            'branch_code' => 'kutisari',
            'discipleship_group_id' => $groupId,
            'group_public_id' => 'group-test',
            'person_id' => $memberId,
            'person_public_id' => 'person-member',
            'role' => 'member',
            'stage' => 'DG 1',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportId = DB::table('discipleship_meeting_reports')->insertGetId([
            'public_id' => 'report-test',
            'branch_code' => 'kutisari',
            'leader_person_id' => $leaderId,
            'leader_person_public_id' => 'person-leader',
            'leader_name_snapshot' => 'Pemimpin Test',
            'discipleship_group_id' => $groupId,
            'discipleship_group_public_id' => 'group-test',
            'group_name_snapshot' => 'Kelompok Test',
            'meeting_date' => '2026-06-01',
            'material_topic' => 'Materi Test',
            'group_progress_snapshot' => 'DG 1',
            'absence_reason' => 'Sakit',
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

        DB::table('discipleship_meeting_report_absences')->insert([
            'discipleship_meeting_report_id' => $reportId,
            'person_id' => $memberId,
            'person_public_id' => 'person-member',
            'person_name_snapshot' => 'Anggota Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
