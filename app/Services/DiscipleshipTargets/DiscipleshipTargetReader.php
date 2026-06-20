<?php

namespace App\Services\DiscipleshipTargets;

use App\Models\DiscipleshipTarget;
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
        $branchCode = $this->branches->normalizeSlug($branchCode);
        $target = DiscipleshipTarget::query()->where('branch_id', $this->branches->idForSlug($branchCode))->first();
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
     * @param  array<int, string>  $branchCodes
     * @return array<string, array<string, int>>
     */
    public function formValuesForBranches(array $branchCodes): array
    {
        $branchIdsByCode = [];
        foreach ($branchCodes as $branchCode) {
            $branchCode = $this->branches->normalizeSlug((string) $branchCode);
            $branchId = $this->branches->idForSlug($branchCode);
            if ($branchCode !== '' && $branchId !== null) {
                $branchIdsByCode[$branchCode] = $branchId;
            }
        }

        if ($branchIdsByCode === []) {
            return [];
        }

        $version = (string) Cache::store($this->cacheStore())->get(self::CACHE_VERSION_KEY, '1');
        $cacheKey = 'rec.discipleship-targets.v2.'.$version.'.'.sha1(json_encode($branchIdsByCode) ?: '[]');
        try {
            $targetRows = Cache::store($this->cacheStore())->remember(
                $cacheKey,
                now()->addMinutes(5),
                static fn (): array => DiscipleshipTarget::query()
                    ->whereIn('branch_id', array_values($branchIdsByCode))
                    ->get()
                    ->mapWithKeys(static fn (DiscipleshipTarget $target): array => [
                        (int) $target->branch_id => $target->only([
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
    public function saveBranch(string $branchCode, array $values): DiscipleshipTarget
    {
        $branchCode = $this->branches->normalizeSlug($branchCode);
        $values = $this->normalizer->normalize($values);

        /** @var DiscipleshipTarget $target */
        $target = DiscipleshipTarget::query()->updateOrCreate(
            ['branch_id' => $this->branches->idForSlug($branchCode)],
            $values,
        );
        Cache::store($this->cacheStore())->put(self::CACHE_VERSION_KEY, (string) hrtime(true));

        return $target;
    }

    private function cacheStore(): string
    {
        return app()->environment('testing') ? 'array' : 'file';
    }
}
