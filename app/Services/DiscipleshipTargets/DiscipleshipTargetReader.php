<?php

namespace App\Services\DiscipleshipTargets;

use App\Models\DiscipleshipTarget;
use Illuminate\Support\Facades\DB;

class DiscipleshipTargetReader
{
    public function __construct(private readonly DiscipleshipTargetNormalizer $normalizer)
    {
    }

    /**
     * @return array<string, int>
     */
    public function valuesForBranch(string $branchCode): array
    {
        $branchCode = normalize_user_branch($branchCode);
        $target = DiscipleshipTarget::query()->where('branch_code', $branchCode)->first();
        if ($target instanceof DiscipleshipTarget) {
            return $this->normalizer->normalize($target->only([
                'camp_gap_participant_target',
                'msk_completion_target',
                'dg1_completion_target',
                'dg2_completion_target',
                'dg3_completion_target',
            ]));
        }

        return $this->normalizer->defaults();
    }

    /**
     * @return array<string, int>
     */
    public function legacyValuesForBranch(string $branchCode): array
    {
        return $this->normalizer->toLegacy($this->valuesForBranch($branchCode));
    }

    /**
     * @param array<string, int> $values
     */
    public function saveBranch(string $branchCode, array $values): DiscipleshipTarget
    {
        $branchCode = normalize_user_branch($branchCode);
        $values = $this->normalizer->normalize($values);

        /** @var DiscipleshipTarget $target */
        $target = DiscipleshipTarget::query()->updateOrCreate(
            ['branch_code' => $branchCode],
            $values,
        );

        $this->syncLegacyTable($branchCode, $values);

        return $target;
    }

    /**
     * @param array<string, int> $values
     */
    public function syncLegacyTable(string $branchCode, array $values): void
    {
        $legacy = $this->normalizer->toLegacy($values);

        DB::table('rec_discipleship_targets')->updateOrInsert(
            ['branch' => $branchCode],
            [
                'dg_total_people' => $legacy['dg_total_people'],
                'msk_completed' => $legacy['msk_completed'],
                'dg1_people' => $legacy['dg1_people'],
                'dg2_people' => $legacy['dg2_people'],
                'dg3_people' => $legacy['dg3_people'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
