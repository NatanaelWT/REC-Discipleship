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
        $this->createTargetTable();
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
        $this->createTargetTable();
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
            ->assertDontSee('Semua Cabang')
            ->assertDontSee('name="target_msk_completed"', false)
            ->assertDontSee('Simpan Target');
    }

    public function test_central_all_filter_defaults_to_first_branch_and_hides_all_option(): void
    {
        $this->createTargetTable();
        $this->seedTargets();
        $this->actingAsRecUser('recpusat', null, 'pemuridan_pusat');

        $this->get('/pemuridan/target?branch_id=all')
            ->assertOk()
            ->assertSee('Rekap aktif: <strong>Kutisari</strong>', false)
            ->assertSee('Cabang Kutisari')
            ->assertSee('class="settings-target-field-value">111</span>', false)
            ->assertDontSee('Semua Cabang');
    }

    public function test_developer_uses_same_readonly_branch_target_layout(): void
    {
        $this->createTargetTable();
        $this->seedTargets();
        $this->actingAsRecUser('developer', null, 'developer');

        $this->get('/pemuridan/target?branch_id=2')
            ->assertOk()
            ->assertSee('Cabang GM')
            ->assertSee('class="settings-target-field-value">222</span>', false)
            ->assertDontSee('name="target_msk_completed"', false)
            ->assertDontSee('Simpan Target');
    }

    public function test_central_and_developer_cannot_update_branch_targets(): void
    {
        $this->createTargetTable();
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

        $this->assertSame(111, (int) DB::table('discipleship_targets')
            ->where('branch_id', 1)
            ->value('camp_gap_participant_target'));
    }

    private function createTargetTable(): void
    {
        Schema::create('discipleship_targets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id')->unique();
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
        DB::table('discipleship_targets')->insert([
            [
                'branch_id' => 1,
                'camp_gap_participant_target' => 111,
                'msk_completion_target' => 111,
                'dg1_completion_target' => 111,
                'dg2_completion_target' => 111,
                'dg3_completion_target' => 111,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 2,
                'camp_gap_participant_target' => 222,
                'msk_completion_target' => 222,
                'dg1_completion_target' => 222,
                'dg2_completion_target' => 222,
                'dg3_completion_target' => 222,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
