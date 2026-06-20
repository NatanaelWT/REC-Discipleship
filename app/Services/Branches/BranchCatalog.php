<?php

namespace App\Services\Branches;

use App\Models\Branch;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class BranchCatalog
{
    /**
     * @return array<int, array{id:int|null,slug:string,label:string}>
     */
    public function options(bool $activeOnly = true): array
    {
        try {
            if (Schema::hasTable('branches')) {
                $query = Branch::query()->orderBy('label');
                if ($activeOnly && Schema::hasColumn('branches', 'is_active')) {
                    $query->where('is_active', true);
                }

                $columns = ['id', 'label'];
                if (Schema::hasColumn('branches', 'code')) {
                    $columns[] = 'code';
                }

                $options = $query->get($columns)
                    ->map(fn (Branch $branch): array => [
                        'id' => (int) $branch->id,
                        'slug' => $this->slugForBranch($branch),
                        'label' => trim((string) $branch->label),
                    ])
                    ->filter(static fn (array $option): bool => $option['slug'] !== '' && $option['slug'] !== 'pusat')
                    ->values()
                    ->all();

                if ($options !== []) {
                    return $options;
                }
            }
        } catch (Throwable) {
            // Database-less test and install flows use the canonical fallback below.
        }

        return self::fallbackOptions();
    }

    public function idForSlug(string $slug): ?int
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        foreach ($this->options() as $option) {
            if ($option['slug'] === $slug && $option['id'] !== null) {
                return $option['id'];
            }
        }

        return null;
    }

    public function slugForId(int|string|null $branchId): string
    {
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($branchId === false) {
            return '';
        }

        foreach ($this->options(false) as $option) {
            if ($option['id'] === $branchId) {
                return $option['slug'];
            }
        }

        return '';
    }

    public function labelForId(int|string|null $branchId): string
    {
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($branchId === false) {
            return 'Tanpa cabang';
        }

        foreach ($this->options(false) as $option) {
            if ($option['id'] === $branchId) {
                return $option['label'];
            }
        }

        return 'Tanpa cabang';
    }

    public function isActiveId(int|string|null $branchId): bool
    {
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($branchId === false) {
            return false;
        }

        foreach ($this->options() as $option) {
            if ($option['id'] === $branchId) {
                return true;
            }
        }

        return false;
    }

    public function normalizeSlug(string $slug): string
    {
        $slug = Str::slug(trim($slug));
        foreach ($this->options() as $option) {
            if ($option['slug'] === $slug) {
                return $slug;
            }
        }

        return '';
    }

    public function defaultId(): ?int
    {
        return $this->idForSlug('kutisari') ?? ($this->options()[0]['id'] ?? null);
    }

    private function slugForBranch(Branch $branch): string
    {
        $storedCode = Schema::hasColumn('branches', 'code')
            ? trim((string) $branch->getAttribute('code'))
            : '';

        return Str::slug($storedCode !== '' ? $storedCode : (string) $branch->label);
    }

    /**
     * @return array<int, array{id:int,slug:string,label:string}>
     */
    private static function fallbackOptions(): array
    {
        return [
            ['id' => 1, 'slug' => 'kutisari', 'label' => 'Kutisari'],
            ['id' => 2, 'slug' => 'gm', 'label' => 'GM'],
            ['id' => 3, 'slug' => 'darmo', 'label' => 'Darmo'],
            ['id' => 4, 'slug' => 'merr', 'label' => 'Merr'],
            ['id' => 5, 'slug' => 'batam', 'label' => 'Batam'],
            ['id' => 6, 'slug' => 'nginden', 'label' => 'Nginden'],
        ];
    }
}
