<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BranchTargetMergeMigrationTest extends TestCase
{
    public function test_migration_moves_targets_to_branches_and_removes_old_table(): void
    {
        $this->createBranches();
        $this->createTargets();
        DB::table('branches')->insert([
            ['id' => 1, 'label' => 'Kutisari', 'is_active' => true],
            ['id' => 2, 'label' => 'GM', 'is_active' => true],
        ]);
        DB::table('discipleship_targets')->insert([
            'branch_id' => 1,
            'camp_gap_participant_target' => 20,
            'msk_completion_target' => 86,
            'dg1_completion_target' => 53,
            'dg2_completion_target' => 27,
            'dg3_completion_target' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_06_21_000005_merge_discipleship_targets_into_branches.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('discipleship_targets'));
        $this->assertTrue(Schema::hasColumns('branches', $this->targetColumns()));
        $this->assertDatabaseHas('branches', [
            'id' => 1,
            'camp_gap_participant_target' => 20,
            'msk_completion_target' => 86,
            'dg1_completion_target' => 53,
            'dg2_completion_target' => 27,
            'dg3_completion_target' => 3,
        ]);
        $this->assertDatabaseHas('branches', [
            'id' => 2,
            'camp_gap_participant_target' => 50,
            'msk_completion_target' => 50,
        ]);

        $migration->down();

        $this->assertTrue(Schema::hasTable('discipleship_targets'));
        $this->assertFalse(Schema::hasColumn('branches', 'camp_gap_participant_target'));
        $this->assertDatabaseHas('discipleship_targets', [
            'branch_id' => 1,
            'camp_gap_participant_target' => 20,
            'msk_completion_target' => 86,
        ]);
    }

    public function test_migration_adds_default_targets_when_old_table_is_absent(): void
    {
        $this->createBranches();
        DB::table('branches')->insert(['id' => 1, 'label' => 'Kutisari', 'is_active' => true]);

        $migration = require database_path('migrations/2026_06_21_000005_merge_discipleship_targets_into_branches.php');
        $migration->up();

        $this->assertDatabaseHas('branches', [
            'id' => 1,
            'camp_gap_participant_target' => 50,
            'msk_completion_target' => 50,
            'dg1_completion_target' => 50,
            'dg2_completion_target' => 50,
            'dg3_completion_target' => 50,
        ]);
    }

    private function createBranches(): void
    {
        Schema::create('branches', static function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function createTargets(): void
    {
        Schema::create('discipleship_targets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id')->unique();
            foreach ($this->targetColumns() as $column) {
                $table->unsignedInteger($column)->default(50);
            }
            $table->timestamps();
        });
    }

    /** @return array<int, string> */
    private function targetColumns(): array
    {
        return [
            'camp_gap_participant_target',
            'msk_completion_target',
            'dg1_completion_target',
            'dg2_completion_target',
            'dg3_completion_target',
        ];
    }
}
