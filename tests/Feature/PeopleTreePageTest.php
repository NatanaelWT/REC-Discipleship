<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PeopleTreePageTest extends TestCase
{
    public function test_legacy_people_tree_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/pohon?page=people_tree');

        $response->assertNotFound();
    }

    public function test_people_tree_v2_route_redirects_to_main_tree_route(): void
    {
        $response = $this->get('/pemuridan/pohon-v2?rekap_cabang=kutisari');

        $response->assertRedirect('/pemuridan/pohon?rekap_cabang=kutisari');
    }

    public function test_people_tree_page_renders_from_laravel_tables(): void
    {
        $this->createTables();
        $this->seedPeopleTree();

        $previousSession = $this->signInAsBranchUser();

        $response = $this->get('/pemuridan/pohon');

        $response->assertStatus(200);
        $response->assertSee('Pohon Pemuridan');
        $response->assertSee('Leader Test');
        $response->assertSee('Anggota Test');
        $response->assertDontSee('?page=people_tree', false);

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
            session_id('people-tree-test-'.str_replace('.', '', uniqid('', true)));
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
        Schema::dropIfExists('msk_participant_photos');
        Schema::dropIfExists('msk_participant_sessions');
        Schema::dropIfExists('msk_participants');
        Schema::dropIfExists('discipleship_group_multiplications');
        Schema::dropIfExists('discipleship_group_leaderships');
        Schema::dropIfExists('discipleship_group_memberships');
        Schema::dropIfExists('discipleship_relationships');
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

    private function seedPeopleTree(): void
    {
        $leaderId = DB::table('discipleship_people')->insertGetId([
            'public_id' => 'person-leader',
            'branch_code' => 'kutisari',
            'member_public_id' => 'member-leader',
            'full_name' => 'Leader Test',
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
            'full_name' => 'Anggota Test',
            'phone' => '0822222222',
            'gender' => 'Perempuan',
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

        DB::table('discipleship_relationships')->insert([
            'public_id' => 'relation-test',
            'branch_code' => 'kutisari',
            'mentor_person_id' => $leaderId,
            'mentor_person_public_id' => 'person-leader',
            'disciple_person_id' => $memberId,
            'disciple_person_public_id' => 'person-member',
            'context_group_id' => $groupId,
            'context_group_public_id' => 'group-test',
            'relation_type' => 'discipleship',
            'stage_at_start' => 'DG 1',
            'status' => 'active',
            'start_date' => '2026-01-01',
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
    }
}
