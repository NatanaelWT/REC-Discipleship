<?php

namespace App\Services\DiscipleshipTargets;

use App\Models\Branch;
use App\Services\Branches\BranchCatalog;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DiscipleshipTargetReader
{
    private const CACHE_VERSION_KEY = 'rec.discipleship-targets.version';

    public function __construct(
        private readonly DiscipleshipTargetNormalizer $normalizer,
        private readonly BranchCatalog $branches,
    ) {}

    /**
     * @return array<string, int>
     */
    public function valuesForBranch(string $branchCode): array
    {
        $branchCode = normalize_user_branch($branchCode);
        $branch = Branch::query()->find(branch_id_from_slug($branchCode));
        if ($branch instanceof Branch) {
            return $this->normalizer->normalize($branch->only([
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
     * @param  array<int, string>  $branchCodes
     * @return array<string, array<string, int>>
     */
    public function formValuesForBranches(array $branchCodes): array
    {
        $branchIdsByCode = [];
        foreach ($branchCodes as $branchCode) {
            $branchCode = normalize_user_branch((string) $branchCode);
            $branchId = branch_id_from_slug($branchCode);
            if ($branchCode !== '' && $branchId !== null) {
                $branchIdsByCode[$branchCode] = $branchId;
            }
        }

        if ($branchIdsByCode === []) {
            return [];
        }

        $version = (string) Cache::store($this->cacheStore())->get(self::CACHE_VERSION_KEY, '1');
        $cacheKey = 'rec.discipleship-targets.v3.'.$version.'.'.sha1(json_encode($branchIdsByCode) ?: '[]');
        try {
            $targetRows = Cache::store($this->cacheStore())->remember(
                $cacheKey,
                now()->addMinutes(5),
                static fn (): array => Branch::query()
                    ->whereIn('id', array_values($branchIdsByCode))
                    ->get()
                    ->mapWithKeys(static fn (Branch $branch): array => [
                        (int) $branch->id => $branch->only([
                            'camp_gap_participant_target',
                            'msk_completion_target',
                            'dg1_completion_target',
                            'dg2_completion_target',
                            'dg3_completion_target',
                        ]),
                    ])
                    ->all(),
            );
        } catch (Throwable) {
            $targetRows = [];
        }

        $values = [];
        foreach ($branchIdsByCode as $branchCode => $branchId) {
            $target = $targetRows[$branchId] ?? null;
            $normalized = is_array($target)
                ? $this->normalizer->normalize($target)
                : $this->normalizer->defaults();
            $values[$branchCode] = $this->normalizer->toFormValues($normalized);
        }

        return $values;
    }

    /**
     * @param  array<string, int>  $values
     */
    public function saveBranch(string $branchCode, array $values): Branch
    {
        $branchCode = normalize_user_branch($branchCode);
        $values = $this->normalizer->normalize($values);

        $branch = Branch::query()->findOrFail(branch_id_from_slug($branchCode));
        $branch->fill($values)->save();
        Cache::store($this->cacheStore())->put(self::CACHE_VERSION_KEY, (string) hrtime(true));

        return $branch;
    }

    private function cacheStore(): string
    {
        return app()->environment('testing') ? 'array' : 'file';
    }
}
