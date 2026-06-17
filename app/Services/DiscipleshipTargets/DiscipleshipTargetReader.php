<?php

namespace App\Services\DiscipleshipTargets;

use App\Models\DiscipleshipTarget;

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
    public function formValuesForBranch(string $branchCode): array
    {
        return $this->normalizer->toFormValues($this->valuesForBranch($branchCode));
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

        return $target;
    }
}
