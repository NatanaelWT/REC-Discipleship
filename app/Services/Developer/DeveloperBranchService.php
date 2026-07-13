<?php

namespace App\Services\Developer;

use App\Models\Branch;
use App\Services\Branches\BranchCatalog;
use App\Services\Discipleship\DiscipleshipReadCache;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeveloperBranchService
{
    /** @var array<string, string> */
    private const TARGET_FIELDS = [
        'camp_gap_participant_target' => 'Target Total Peserta DG',
        'msk_completion_target' => 'Target Selesai MSK',
        'dg1_completion_target' => 'Target DG 1',
        'dg2_completion_target' => 'Target DG 2',
        'dg3_completion_target' => 'Target DG 3',
    ];

    /** @var array<string, string> */
    private const USAGE_TABLES = [
        'users' => 'User',
        'orang' => 'Orang',
        'kelompok_dg' => 'Kelompok DG',
        'keanggotaan_kelompok_dg' => 'Keanggotaan DG',
        'jurnal_temu_dg' => 'Jurnal Temu DG',
        'jurnal_umpan_balik' => 'Feedback Anggota',
        'dg_manual' => 'Manual Journey',
    ];

    public function __construct(
        private readonly BranchCatalog $branches,
        private readonly DiscipleshipReadCache $readCache,
        private readonly DiscipleshipTargetReader $targetReader,
    ) {}

    /**
     * @return array<int, array{id:int|null,code:string,label:string}>
     */
    public function options(): array
    {
        return array_map(static fn (array $option): array => [
            'id' => $option['id'],
            'code' => $option['slug'],
            'label' => $option['label'],
        ], $this->branches->options());
    }

    public function normalizeAllowedId(mixed $branchId): ?int
    {
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($branchId === false || ! $this->branches->isActiveId($branchId)) {
            return null;
        }

        return $branchId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminRows(): array
    {
        return Branch::query()
            ->orderBy('label')
            ->get()
            ->filter(fn (Branch $branch): bool => ! $this->isProtectedBranch($branch))
            ->map(function (Branch $branch): array {
                $usage = $this->usageCounts((int) $branch->getKey());

                return [
                    'id' => (int) $branch->getKey(),
                    'label' => trim((string) $branch->label),
                    'slug' => Str::slug(trim((string) $branch->label)),
                    'active' => (bool) $branch->is_active,
                    'targets' => $this->targetValues($branch),
                    'usage' => $usage['counts'],
                    'usage_total' => $usage['total'],
                    'can_delete' => $usage['total'] === 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{label:string,value:string}>
     */
    public function stats(array $rows): array
    {
        $total = count($rows);
        $active = count(array_filter($rows, static fn (array $row): bool => (bool) ($row['active'] ?? false)));
        $inactive = max(0, $total - $active);
        $empty = count(array_filter($rows, static fn (array $row): bool => (bool) ($row['can_delete'] ?? false)));

        return [
            ['label' => 'Total Cabang', 'value' => number_format($total, 0, ',', '.')],
            ['label' => 'Produksi Aktif', 'value' => number_format($active, 0, ',', '.')],
            ['label' => 'Mode Eksperimen', 'value' => number_format($inactive, 0, ',', '.')],
            ['label' => 'Cabang Kosong', 'value' => number_format($empty, 0, ',', '.')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function targetFields(): array
    {
        return self::TARGET_FIELDS;
    }

    public function create(array $input): ?string
    {
        $attributes = $this->attributesFromInput($input, null, false);
        if (is_string($attributes)) {
            return $attributes;
        }

        DB::transaction(static function () use ($attributes): void {
            Branch::query()->create($attributes);
        });
        $this->invalidateCaches();

        return null;
    }

    public function update(Branch $branch, array $input): ?string
    {
        if ($this->isProtectedBranch($branch)) {
            return 'branch_protected';
        }

        $attributes = $this->attributesFromInput($input, $branch, (bool) $branch->is_active);
        if (is_string($attributes)) {
            return $attributes;
        }

        DB::transaction(static function () use ($branch, $attributes): void {
            $branch->fill($attributes)->save();
        });
        $this->invalidateCaches();

        return null;
    }

    public function delete(Branch $branch): ?string
    {
        if ($this->isProtectedBranch($branch)) {
            return 'branch_protected';
        }

        if ($this->usageCounts((int) $branch->getKey())['total'] > 0) {
            return 'branch_not_empty';
        }

        DB::transaction(static function () use ($branch): void {
            $branch->delete();
        });
        $this->invalidateCaches();

        return null;
    }

    /**
     * @return array<string, mixed>|string
     */
    private function attributesFromInput(array $input, ?Branch $current, bool $defaultActive): array|string
    {
        $label = $this->normalizeLabel((string) ($input['label'] ?? ''));
        if ($label === '') {
            return 'missing_required';
        }

        $slug = Str::slug($label);
        if ($slug === '') {
            return 'label_invalid';
        }
        if (in_array($slug, ['all', 'pusat'], true)) {
            return 'slug_reserved';
        }
        if ($this->hasLabelOrSlugConflict($label, $slug, $current)) {
            return 'label_taken';
        }

        $targets = $this->targetAttributesFromInput($input, $current);
        if (is_string($targets)) {
            return $targets;
        }

        return array_merge([
            'label' => $label,
            'is_active' => array_key_exists('is_active', $input)
                ? $this->boolFromInput($input['is_active'])
                : $defaultActive,
        ], $targets);
    }

    /**
     * @return array<string, int>|string
     */
    private function targetAttributesFromInput(array $input, ?Branch $current): array|string
    {
        $targets = [];
        foreach (self::TARGET_FIELDS as $field => $label) {
            $default = $current instanceof Branch ? (int) ($current->{$field} ?? 50) : 50;
            $value = $input[$field] ?? $default;
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === '' || ! is_numeric($value) || (is_string($value) && ! ctype_digit($value))) {
                return 'target_invalid';
            }

            $target = (int) $value;
            if ($target < 0 || $target > 1000000) {
                return 'target_invalid';
            }

            $targets[$field] = $target;
        }

        return $targets;
    }

    private function normalizeLabel(string $label): string
    {
        $label = preg_replace('/\s+/', ' ', trim($label)) ?? '';

        return function_exists('mb_substr') ? mb_substr($label, 0, 120) : substr($label, 0, 120);
    }

    private function hasLabelOrSlugConflict(string $label, string $slug, ?Branch $current): bool
    {
        $currentId = $current instanceof Branch ? (int) $current->getKey() : null;
        $labelKey = Str::lower($label);

        return Branch::query()
            ->get(['id', 'label'])
            ->contains(static function (Branch $branch) use ($currentId, $labelKey, $slug): bool {
                if ($currentId !== null && (int) $branch->getKey() === $currentId) {
                    return false;
                }

                $otherLabel = trim((string) $branch->label);

                return Str::lower($otherLabel) === $labelKey || Str::slug($otherLabel) === $slug;
            });
    }

    private function boolFromInput(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function isProtectedBranch(Branch $branch): bool
    {
        return Str::slug(trim((string) $branch->label)) === 'pusat';
    }

    /**
     * @return array<string, int>
     */
    private function targetValues(Branch $branch): array
    {
        $values = [];
        foreach (self::TARGET_FIELDS as $field => $label) {
            $values[$field] = (int) ($branch->{$field} ?? 50);
        }

        return $values;
    }

    /**
     * @return array{counts:array<string, int>, total:int}
     */
    private function usageCounts(int $branchId): array
    {
        $counts = [];
        $total = 0;
        foreach (self::USAGE_TABLES as $table => $label) {
            $count = $this->countBranchRows($table, $branchId);
            $counts[$label] = $count;
            $total += $count;
        }

        return ['counts' => $counts, 'total' => $total];
    }

    private function countBranchRows(string $table, int $branchId): int
    {
        return (int) DB::table($table)->where('branch_id', $branchId)->count();
    }

    private function invalidateCaches(): void
    {
        $this->branches->clearCache();
        $this->targetReader->invalidateCache();
        $this->readCache->invalidate();
    }
}
