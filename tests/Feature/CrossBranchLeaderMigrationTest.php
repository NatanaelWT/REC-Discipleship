<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrossBranchLeaderMigrationTest extends TestCase
{
    public function test_yakub_gm_missing_gender_is_filled_idempotently(): void
    {
        $this->createTables();
        $this->seedYakubRows();

        $migration = require database_path('migrations/2026_07_01_000002_fill_yakub_tri_handoko_gm_gender.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('discipleship_people', [
            'id' => 626,
            'branch_id' => 2,
            'full_name' => 'Yakub Tri Handoko',
            'gender' => 'Laki-laki',
        ]);
        $this->assertNull(DB::table('discipleship_people')->where('id', 776)->value('gender'));
        $this->assertNull(DB::table('discipleship_people')->where('id', 790)->value('gender'));
    }

    public function test_yakub_kutisari_external_is_relinked_to_gm_person_idempotently(): void
    {
        $this->createTables();
        $this->seedYakubRows();

        $migration = require database_path('migrations/2026_07_01_000001_relink_yakub_tri_handoko_cross_branch_leader.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('discipleship_group_people', [
            'branch_id' => 1,
            'discipleship_group_id' => 175,
            'person_id' => 626,
            'role' => 'leader',
        ]);
        $this->assertDatabaseMissing('discipleship_group_people', [
            'branch_id' => 1,
            'person_id' => 790,
            'role' => 'leader',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 1,
            'mentor_person_id' => 626,
            'disciple_person_id' => 641,
        ]);
        $this->assertDatabaseHas('discipleship_groups', [
            'id' => 176,
            'branch_id' => 1,
            'initiated_by_person_id' => 626,
        ]);
        $this->assertDatabaseHas('discipleship_meeting_reports', [
            'branch_id' => 1,
            'leader_person_id' => 626,
        ]);
        $this->assertDatabaseHas('discipleship_feedbacks', [
            'branch_id' => 1,
            'leader_person_id' => 626,
        ]);
        $this->assertDatabaseHas('discipleship_people', [
            'id' => 790,
            'branch_id' => 1,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 1,
            'disciple_person_id' => 790,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);
        $this->assertDatabaseHas('discipleship_group_people', [
            'branch_id' => 3,
            'person_id' => 776,
            'role' => 'leader',
        ]);
    }

    public function test_yakub_darmo_external_is_relinked_to_gm_person_idempotently(): void
    {
        $this->createTables();
        $this->seedYakubRows();

        $migration = require database_path('migrations/2026_07_01_000003_relink_yakub_tri_handoko_darmo_to_gm.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('discipleship_group_people', [
            'branch_id' => 3,
            'discipleship_group_id' => 300,
            'person_id' => 626,
            'role' => 'leader',
        ]);
        $this->assertDatabaseMissing('discipleship_group_people', [
            'branch_id' => 3,
            'person_id' => 776,
            'role' => 'leader',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 3,
            'mentor_person_id' => 626,
            'disciple_person_id' => 641,
        ]);
        $this->assertDatabaseHas('discipleship_groups', [
            'id' => 300,
            'branch_id' => 3,
            'initiated_by_person_id' => 626,
        ]);
        $this->assertDatabaseHas('discipleship_group_multiplications', [
            'branch_id' => 3,
            'initiated_by_person_id' => 626,
        ]);
        $this->assertDatabaseHas('discipleship_meeting_reports', [
            'branch_id' => 3,
            'leader_person_id' => 626,
        ]);
        $this->assertDatabaseHas('discipleship_feedbacks', [
            'branch_id' => 3,
            'leader_person_id' => 626,
            'respondent_person_id' => 626,
        ]);
        $this->assertDatabaseHas('discipleship_people', [
            'id' => 776,
            'branch_id' => 3,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 3,
            'disciple_person_id' => 776,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);
        $this->assertDatabaseHas('discipleship_group_people', [
            'branch_id' => 1,
            'person_id' => 790,
            'role' => 'leader',
        ]);
    }

    public function test_marliana_nginden_external_is_relinked_to_kutisari_person_idempotently(): void
    {
        $this->createTables();
        $this->seedMarlianaRows();

        $migration = require database_path('migrations/2026_07_01_000004_relink_marliana_nginden_to_kutisari.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('discipleship_group_people', [
            'branch_id' => 6,
            'discipleship_group_id' => 226,
            'person_id' => 664,
            'role' => 'leader',
        ]);
        $this->assertDatabaseMissing('discipleship_group_people', [
            'branch_id' => 6,
            'person_id' => 854,
            'role' => 'leader',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 6,
            'mentor_person_id' => 664,
            'disciple_person_id' => 704,
        ]);
        $this->assertDatabaseHas('discipleship_groups', [
            'id' => 226,
            'branch_id' => 6,
            'initiated_by_person_id' => 664,
        ]);
        $this->assertDatabaseHas('discipleship_group_multiplications', [
            'branch_id' => 6,
            'initiated_by_person_id' => 664,
        ]);
        $this->assertDatabaseHas('discipleship_meeting_reports', [
            'branch_id' => 6,
            'leader_person_id' => 664,
        ]);
        $this->assertDatabaseHas('discipleship_feedbacks', [
            'branch_id' => 6,
            'leader_person_id' => 664,
            'respondent_person_id' => 664,
        ]);
        $this->assertDatabaseHas('discipleship_people', [
            'id' => 854,
            'branch_id' => 6,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 6,
            'disciple_person_id' => 854,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);
        $this->assertDatabaseHas('discipleship_people', [
            'id' => 664,
            'branch_id' => 1,
            'status' => 'active',
        ]);
    }

    public function test_david_antonius_and_anneke_externals_are_relinked_to_gm_people_idempotently(): void
    {
        $this->createTables();
        $this->seedDavidAndAnnekeRows();

        $migration = require database_path('migrations/2026_07_01_000005_relink_david_antonius_and_anneke_to_gm.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('discipleship_group_people', [
            'branch_id' => 4,
            'discipleship_group_id' => 220,
            'person_id' => 587,
            'role' => 'leader',
        ]);
        $this->assertDatabaseHas('discipleship_group_people', [
            'branch_id' => 3,
            'discipleship_group_id' => 193,
            'person_id' => 583,
            'role' => 'leader',
        ]);
        $this->assertDatabaseMissing('discipleship_group_people', [
            'branch_id' => 4,
            'person_id' => 850,
            'role' => 'leader',
        ]);
        $this->assertDatabaseMissing('discipleship_group_people', [
            'branch_id' => 3,
            'person_id' => 774,
            'role' => 'leader',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 4,
            'mentor_person_id' => 587,
            'disciple_person_id' => 846,
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 3,
            'mentor_person_id' => 583,
            'disciple_person_id' => 405,
        ]);
        $this->assertDatabaseHas('discipleship_groups', [
            'id' => 220,
            'branch_id' => 4,
            'initiated_by_person_id' => 587,
        ]);
        $this->assertDatabaseHas('discipleship_groups', [
            'id' => 193,
            'branch_id' => 3,
            'initiated_by_person_id' => 583,
        ]);
        $this->assertDatabaseHas('discipleship_meeting_reports', [
            'branch_id' => 4,
            'leader_person_id' => 587,
        ]);
        $this->assertDatabaseHas('discipleship_feedbacks', [
            'branch_id' => 3,
            'leader_person_id' => 583,
            'respondent_person_id' => 583,
        ]);
        $this->assertDatabaseHas('discipleship_people', [
            'id' => 850,
            'branch_id' => 4,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('discipleship_people', [
            'id' => 774,
            'branch_id' => 3,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 4,
            'disciple_person_id' => 850,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 3,
            'disciple_person_id' => 774,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);
    }

    public function test_relinked_external_people_are_hard_deleted_after_history_moves_to_canonical_people(): void
    {
        $this->createTables();
        $this->seedYakubRows();
        $this->seedMarlianaRows();
        $this->seedDavidAndAnnekeRows();

        foreach ([
            '2026_07_01_000001_relink_yakub_tri_handoko_cross_branch_leader.php',
            '2026_07_01_000003_relink_yakub_tri_handoko_darmo_to_gm.php',
            '2026_07_01_000004_relink_marliana_nginden_to_kutisari.php',
            '2026_07_01_000005_relink_david_antonius_and_anneke_to_gm.php',
        ] as $migrationFile) {
            $migration = require database_path('migrations/'.$migrationFile);
            $migration->up();
        }

        $migration = require database_path('migrations/2026_07_01_000006_hard_delete_relinked_external_people.php');
        $migration->up();
        $migration->up();

        foreach ([790, 776, 854, 850, 774] as $externalPersonId) {
            $this->assertDatabaseMissing('discipleship_people', [
                'id' => $externalPersonId,
            ]);
        }

        $this->assertNoExternalPersonReferences([790, 776, 854, 850, 774]);

        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 1,
            'mentor_person_id' => null,
            'disciple_person_id' => 626,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 3,
            'mentor_person_id' => null,
            'disciple_person_id' => 626,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 6,
            'mentor_person_id' => null,
            'disciple_person_id' => 664,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 4,
            'mentor_person_id' => null,
            'disciple_person_id' => 587,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'branch_id' => 3,
            'mentor_person_id' => null,
            'disciple_person_id' => 583,
            'status' => 'closed',
            'reason_end' => 'converted_to_cross_branch_leader',
        ]);

        $report = DB::table('discipleship_meeting_reports')
            ->where('branch_id', 4)
            ->where('leader_person_id', 587)
            ->first(['absences', 'meditation_sharers']);

        $this->assertNotNull($report);
        $this->assertStringContainsString('"person_id":587', (string) $report->absences);
        $this->assertStringContainsString('"person_id":"587"', (string) $report->meditation_sharers);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('discipleship_feedbacks');
        Schema::dropIfExists('discipleship_meeting_reports');
        Schema::dropIfExists('discipleship_group_multiplications');
        Schema::dropIfExists('discipleship_group_people');
        Schema::dropIfExists('discipleship_relationships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name')->default('Kelompok');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->unsignedBigInteger('context_group_id')->nullable();
            $table->string('status')->default('active');
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_multiplications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_meeting_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->longText('absences')->nullable();
            $table->longText('meditation_sharers')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->unsignedBigInteger('respondent_person_id')->nullable();
            $table->timestamps();
        });
    }

    private function seedYakubRows(): void
    {
        DB::table('discipleship_people')->insert([
            ['id' => 626, 'branch_id' => 2, 'full_name' => 'Yakub Tri Handoko', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 776, 'branch_id' => 3, 'full_name' => 'Yakub Tri Handoko', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 790, 'branch_id' => 1, 'full_name' => 'Yakub Tri Handoko', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 641, 'branch_id' => 1, 'full_name' => 'Anggota Kutisari', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_groups')->insert([
            ['id' => 175, 'branch_id' => 1, 'name' => 'Kelompok', 'status' => 'completed', 'initiated_by_person_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 176, 'branch_id' => 1, 'name' => 'Kelompok', 'status' => 'completed', 'initiated_by_person_id' => 790, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 300, 'branch_id' => 3, 'name' => 'Kelompok Darmo', 'status' => 'completed', 'initiated_by_person_id' => 776, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_group_people')->insert([
            ['branch_id' => 1, 'discipleship_group_id' => 175, 'person_id' => 790, 'role' => 'leader', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 3, 'discipleship_group_id' => 300, 'person_id' => 776, 'role' => 'leader', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_relationships')->insert([
            ['branch_id' => 1, 'mentor_person_id' => 790, 'disciple_person_id' => 641, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 1, 'mentor_person_id' => null, 'disciple_person_id' => 790, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 3, 'mentor_person_id' => 776, 'disciple_person_id' => 641, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 3, 'mentor_person_id' => null, 'disciple_person_id' => 776, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_group_multiplications')->insert([
            ['branch_id' => 1, 'initiated_by_person_id' => 790, 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 3, 'initiated_by_person_id' => 776, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_meeting_reports')->insert([
            ['branch_id' => 1, 'leader_person_id' => 790, 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 3, 'leader_person_id' => 776, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_feedbacks')->insert([
            ['branch_id' => 1, 'leader_person_id' => 790, 'respondent_person_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 3, 'leader_person_id' => 776, 'respondent_person_id' => 776, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function seedMarlianaRows(): void
    {
        DB::table('discipleship_people')->insert([
            ['id' => 664, 'branch_id' => 1, 'full_name' => 'Marliana', 'gender' => 'Perempuan', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 854, 'branch_id' => 6, 'full_name' => 'Marliana', 'gender' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 704, 'branch_id' => 6, 'full_name' => 'Anggota Nginden', 'gender' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_groups')->insert([
            ['id' => 224, 'branch_id' => 6, 'name' => 'Kelompok', 'status' => 'completed', 'initiated_by_person_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 225, 'branch_id' => 6, 'name' => 'Kelompok', 'status' => 'completed', 'initiated_by_person_id' => 854, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 226, 'branch_id' => 6, 'name' => 'Kelompok', 'status' => 'active', 'initiated_by_person_id' => 854, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_group_people')->insert([
            ['branch_id' => 6, 'discipleship_group_id' => 224, 'person_id' => 854, 'role' => 'leader', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 6, 'discipleship_group_id' => 225, 'person_id' => 854, 'role' => 'leader', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 6, 'discipleship_group_id' => 226, 'person_id' => 854, 'role' => 'leader', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_relationships')->insert([
            ['branch_id' => 6, 'mentor_person_id' => 854, 'disciple_person_id' => 704, 'context_group_id' => 224, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 6, 'mentor_person_id' => 854, 'disciple_person_id' => 704, 'context_group_id' => 226, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 6, 'mentor_person_id' => null, 'disciple_person_id' => 854, 'context_group_id' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_group_multiplications')->insert([
            'branch_id' => 6,
            'initiated_by_person_id' => 854,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_meeting_reports')->insert([
            'branch_id' => 6,
            'leader_person_id' => 854,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_feedbacks')->insert([
            'branch_id' => 6,
            'leader_person_id' => 854,
            'respondent_person_id' => 854,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedDavidAndAnnekeRows(): void
    {
        DB::table('discipleship_people')->insert([
            ['id' => 583, 'branch_id' => 2, 'full_name' => 'Anneke Aryanti', 'gender' => 'Perempuan', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 587, 'branch_id' => 2, 'full_name' => 'David Antonius', 'gender' => 'Laki-laki', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 774, 'branch_id' => 3, 'full_name' => 'Anneke', 'gender' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 850, 'branch_id' => 4, 'full_name' => 'David Antonius', 'gender' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 405, 'branch_id' => 3, 'full_name' => 'Anggota Darmo', 'gender' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 846, 'branch_id' => 4, 'full_name' => 'Anggota Merr', 'gender' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_groups')->insert([
            ['id' => 193, 'branch_id' => 3, 'name' => 'Kelompok Darmo', 'status' => 'completed', 'initiated_by_person_id' => 774, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 220, 'branch_id' => 4, 'name' => 'Kelompok Merr', 'status' => 'active', 'initiated_by_person_id' => 850, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_group_people')->insert([
            ['branch_id' => 3, 'discipleship_group_id' => 193, 'person_id' => 774, 'role' => 'leader', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 4, 'discipleship_group_id' => 220, 'person_id' => 850, 'role' => 'leader', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_relationships')->insert([
            ['branch_id' => 3, 'mentor_person_id' => 774, 'disciple_person_id' => 405, 'context_group_id' => 193, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 3, 'mentor_person_id' => null, 'disciple_person_id' => 774, 'context_group_id' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 4, 'mentor_person_id' => 850, 'disciple_person_id' => 846, 'context_group_id' => 220, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 4, 'mentor_person_id' => null, 'disciple_person_id' => 850, 'context_group_id' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_group_multiplications')->insert([
            ['branch_id' => 3, 'initiated_by_person_id' => 774, 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 4, 'initiated_by_person_id' => 850, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_meeting_reports')->insert([
            [
                'branch_id' => 3,
                'leader_person_id' => 774,
                'absences' => null,
                'meditation_sharers' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 4,
                'leader_person_id' => 850,
                'absences' => '[{"person_id":850,"person_name_snapshot":"David Antonius"}]',
                'meditation_sharers' => '[{"person_id":"850","person_name_snapshot":"David Antonius"}]',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('discipleship_feedbacks')->insert([
            ['branch_id' => 3, 'leader_person_id' => 774, 'respondent_person_id' => 774, 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 4, 'leader_person_id' => 850, 'respondent_person_id' => 850, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** @param array<int, int> $externalPersonIds */
    private function assertNoExternalPersonReferences(array $externalPersonIds): void
    {
        foreach ([
            ['discipleship_group_people', 'person_id'],
            ['discipleship_relationships', 'mentor_person_id'],
            ['discipleship_relationships', 'disciple_person_id'],
            ['discipleship_groups', 'initiated_by_person_id'],
            ['discipleship_group_multiplications', 'initiated_by_person_id'],
            ['discipleship_meeting_reports', 'leader_person_id'],
            ['discipleship_feedbacks', 'leader_person_id'],
            ['discipleship_feedbacks', 'respondent_person_id'],
        ] as [$table, $column]) {
            $count = DB::table($table)->whereIn($column, $externalPersonIds)->count();

            $this->assertSame(0, $count, $table.'.'.$column.' still references an external person');
        }
    }
}
