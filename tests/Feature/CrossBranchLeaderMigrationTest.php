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
            $table->timestamps();
        });

        Schema::create('discipleship_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
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
        ]);
        DB::table('discipleship_group_people')->insert([
            ['branch_id' => 1, 'discipleship_group_id' => 175, 'person_id' => 790, 'role' => 'leader', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 3, 'discipleship_group_id' => 300, 'person_id' => 776, 'role' => 'leader', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_relationships')->insert([
            ['branch_id' => 1, 'mentor_person_id' => 790, 'disciple_person_id' => 641, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 1, 'mentor_person_id' => null, 'disciple_person_id' => 790, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 3, 'mentor_person_id' => 776, 'disciple_person_id' => 641, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_group_multiplications')->insert([
            'branch_id' => 1,
            'initiated_by_person_id' => 790,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_meeting_reports')->insert([
            'branch_id' => 1,
            'leader_person_id' => 790,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_feedbacks')->insert([
            'branch_id' => 1,
            'leader_person_id' => 790,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
