<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscipleshipTargetPageTest extends TestCase
{
    public function test_branch_user_keeps_editable_target_card_layout(): void
    {
        $this->createBranchTable();
        $this->seedTargets();
        $this->actingAsRecUser();

        $this->get('/pemuridan/target')
            ->assertOk()
            ->assertSee('Cabang Kutisari')
            ->assertSee('class="card settings-target-card"', false)
            ->assertSee('name="target_msk_completed"', false)
            ->assertSee('Simpan Target');
    }

    public function test_central_user_filters_one_branch_in_readonly_card_layout(): void
    {
        $this->createBranchTable();
        $this->seedTargets();
        $this->actingAsRecUser('recpusat', null, 'pemuridan_pusat');

        $this->get('/pemuridan/target?branch_id=2')
            ->assertOk()
            ->assertSee('Mode Pusat')
            ->assertSee('Rekap aktif: <strong>GM</strong>', false)
            ->assertSee('Cabang GM')
            ->assertSee('class="card settings-target-card"', false)
            ->assertSee('class="settings-target-field-value">222</span>', false)
            ->assertSee('Hanya Lihat')
            ->assertSee('Semua Cabang')
            ->assertDontSee('name="target_msk_completed"', false)
            ->assertDontSee('Simpan Target');
    }

    public function test_central_all_filter_groups_every_branch_by_target_type(): void
    {
        $this->createBranchTable();
        $this->seedTargets();
        $this->actingAsRecUser('recpusat', null, 'pemuridan_pusat');

        $this->get('/pemuridan/target?branch_id=all')
            ->assertOk()
            ->assertSee('Rekap aktif: <strong>Semua Cabang</strong>', false)
            ->assertSee('data-target-section="msk_completed"', false)
            ->assertSee('data-target-section="dg1_people"', false)
            ->assertSee('data-target-section="dg_total_people"', false)
            ->assertSee('data-target-section="dg2_people"', false)
            ->assertSee('data-target-section="dg3_people"', false)
            ->assertSeeInOrder([
                'data-target-section="msk_completed"',
                'Target Total Selesai MSK',
                'data-branch-code="gm"',
                '<strong>222</strong>',
                'data-branch-code="kutisari"',
                '<strong>112</strong>',
                'data-target-section="dg1_people"',
                '<strong>223</strong>',
                '<strong>113</strong>',
            ], false)
            ->assertDontSee('name="target_msk_completed"', false)
            ->assertDontSee('Simpan Target');
    }

    public function test_developer_can_view_readonly_targets_for_all_branches(): void
    {
        $this->createBranchTable();
        $this->seedTargets();
        $this->actingAsRecUser('developer', null, 'developer');

        $this->get('/pemuridan/target?branch_id=all')
            ->assertOk()
            ->assertSee('Rekap aktif: <strong>Semua Cabang</strong>', false)
            ->assertSee('data-target-section="msk_completed"', false)
            ->assertSee('data-branch-code="gm"', false)
            ->assertSee('<strong>222</strong>', false)
            ->assertDontSee('name="target_msk_completed"', false)
            ->assertDontSee('Simpan Target');
    }

    public function test_central_and_developer_cannot_update_branch_targets(): void
    {
        $this->createBranchTable();
        $this->seedTargets();

        foreach (['pemuridan_pusat', 'developer'] as $scope) {
            $this->actingAsRecUser($scope, null, $scope);

            $this->post('/pemuridan/target', [
                'action' => 'save_discipleship_targets',
                'target_dg_total_people' => 999,
                'target_msk_completed' => 999,
                'target_dg1_people' => 999,
                'target_dg2_people' => 999,
                'target_dg3_people' => 999,
            ])->assertRedirect();
        }

        $this->assertSame(111, (int) DB::table('branches')
            ->where('id', 1)
            ->value('camp_gap_participant_target'));
    }

    private function createBranchTable(): void
    {
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
    }

    private function seedTargets(): void
    {
        DB::table('branches')->insert([
            [
                'id' => 1,
                'label' => 'Kutisari',
                'is_active' => true,
                'camp_gap_participant_target' => 111,
                'msk_completion_target' => 112,
                'dg1_completion_target' => 113,
                'dg2_completion_target' => 114,
                'dg3_completion_target' => 115,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'label' => 'GM',
                'is_active' => true,
                'camp_gap_participant_target' => 221,
                'msk_completion_target' => 222,
                'dg1_completion_target' => 223,
                'dg2_completion_target' => 224,
                'dg3_completion_target' => 225,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
