<?php

namespace Tests\Feature;

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

        $previousSession = $this->signInAsBranchUser();

        $response = $this->get('/pemuridan/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Dashboard Pemuridan');
        $response->assertSee('Belum Selesai MSK');
        $response->assertSee('Peserta MSK Dashboard');
        $response->assertDontSee('?page=discipleship_dashboard', false);

        $this->restoreSession($previousSession);
    }

    public function test_dashboard_updates_msk_sessions_to_laravel_tables(): void
    {
        $this->createDashboardTables();
        $this->seedDashboardData();

        $previousSession = $this->signInAsBranchUser();

        $response = $this->post('/pemuridan/dashboard/msk-sessions', [
            'action' => 'save_msk_sessions',
            'id' => 'msk-dashboard',
            'return_page' => 'discipleship_dashboard',
            'session_numbers' => ['1', '2', '3', '4'],
        ]);

        $response->assertRedirect('/pemuridan/dashboard?msk_session_saved=1');

        $participantId = (int) DB::table('msk_participants')
            ->where('public_id', 'msk-dashboard')
            ->value('id');

        $this->assertDatabaseHas('msk_participant_sessions', [
            'msk_participant_id' => $participantId,
            'session_number' => 4,
        ]);
        $this->assertSame(4, DB::table('msk_participant_sessions')->where('msk_participant_id', $participantId)->count());

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
            session_id('dashboard-test-' . str_replace('.', '', uniqid('', true)));
            session_start();
        }
        $_SESSION['user'] = 'tester';
        $_SESSION['cabang'] = 'kutisari';
        $_SESSION['access_scope'] = 'branch';

        return $previousSession;
    }

    /**
     * @param array<string, mixed> $previousSession
     */
    private function restoreSession(array $previousSession): void
    {
        $_SESSION = $previousSession;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function createDashboardTables(): void
    {
        Schema::dropIfExists('msk_participant_photos');
        Schema::dropIfExists('msk_participant_sessions');
        Schema::dropIfExists('msk_participants');
        Schema::dropIfExists('discipleship_group_multiplications');
        Schema::dropIfExists('discipleship_group_leaderships');
        Schema::dropIfExists('discipleship_group_memberships');
        Schema::dropIfExists('discipleship_relationships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');
        Schema::dropIfExists('discipleship_targets');

        Schema::create('discipleship_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('branch_code', 40)->unique();
            $table->unsignedInteger('camp_gap_participant_target')->default(50);
            $table->unsignedInteger('msk_completion_target')->default(50);
            $table->unsignedInteger('dg1_completion_target')->default(50);
            $table->unsignedInteger('dg2_completion_target')->default(50);
            $table->unsignedInteger('dg3_completion_target')->default(50);
            $table->timestamps();
        });

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

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->string('mentor_person_public_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->string('disciple_person_public_id')->nullable();
            $table->unsignedBigInteger('context_group_id')->nullable();
            $table->string('context_group_public_id')->nullable();
            $table->string('relation_type')->nullable();
            $table->string('stage_at_start')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
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

        Schema::create('discipleship_group_leaderships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
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

        Schema::create('discipleship_group_multiplications', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->string('initiated_by_person_public_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->string('source_group_public_id')->nullable();
            $table->unsignedBigInteger('new_group_id')->nullable();
            $table->string('new_group_public_id')->nullable();
            $table->date('multiplication_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->string('branch_code', 40);
            $table->string('member_public_id')->nullable();
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
            $table->timestamps();
        });

        Schema::create('msk_participant_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('msk_participant_id');
            $table->unsignedTinyInteger('session_number');
            $table->timestamps();
        });

        Schema::create('msk_participant_photos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('msk_participant_id');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    private function seedDashboardData(): void
    {
        DB::table('discipleship_targets')->insert([
            'branch_code' => 'kutisari',
            'camp_gap_participant_target' => 10,
            'msk_completion_target' => 10,
            'dg1_completion_target' => 10,
            'dg2_completion_target' => 10,
            'dg3_completion_target' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $leaderId = DB::table('discipleship_people')->insertGetId([
            'public_id' => 'person-leader',
            'branch_code' => 'kutisari',
            'member_public_id' => 'member-leader',
            'full_name' => 'Leader Dashboard',
            'phone' => '0811111111',
            'gender' => 'Laki-laki',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('discipleship_people')->insertGetId([
            'public_id' => 'person-member',
            'branch_code' => 'kutisari',
            'member_public_id' => 'member-member',
            'full_name' => 'Anggota Dashboard',
            'phone' => '0822222222',
            'gender' => 'Perempuan',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('discipleship_groups')->insertGetId([
            'public_id' => 'group-dashboard',
            'branch_code' => 'kutisari',
            'name' => 'Kelompok Dashboard',
            'status' => 'active',
            'start_stage' => 'DG 1',
            'current_stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_group_leaderships')->insert([
            'public_id' => 'leadership-dashboard',
            'branch_code' => 'kutisari',
            'discipleship_group_id' => $groupId,
            'group_public_id' => 'group-dashboard',
            'person_id' => $leaderId,
            'person_public_id' => 'person-leader',
            'role' => 'leader',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_group_memberships')->insert([
            'public_id' => 'membership-dashboard',
            'branch_code' => 'kutisari',
            'discipleship_group_id' => $groupId,
            'group_public_id' => 'group-dashboard',
            'person_id' => $memberId,
            'person_public_id' => 'person-member',
            'role' => 'member',
            'stage' => 'DG 1',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_relationships')->insert([
            'public_id' => 'relation-dashboard',
            'branch_code' => 'kutisari',
            'mentor_person_id' => $leaderId,
            'mentor_person_public_id' => 'person-leader',
            'disciple_person_id' => $memberId,
            'disciple_person_public_id' => 'person-member',
            'context_group_id' => $groupId,
            'context_group_public_id' => 'group-dashboard',
            'relation_type' => 'discipleship',
            'stage_at_start' => 'DG 1',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $participantId = DB::table('msk_participants')->insertGetId([
            'public_id' => 'msk-dashboard',
            'branch_code' => 'kutisari',
            'member_public_id' => 'member-msk-dashboard',
            'full_name' => 'Peserta MSK Dashboard',
            'gender' => 'Laki-laki',
            'whatsapp' => '0833333333',
            'batch_month' => '2026-06',
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('msk_participant_sessions')->insert([
            [
                'msk_participant_id' => $participantId,
                'session_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'msk_participant_id' => $participantId,
                'session_number' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
