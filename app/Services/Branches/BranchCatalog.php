<?php

namespace App\Services\Branches;

use App\Models\Branch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class BranchCatalog
{
    private const CACHE_KEY = 'rec.branch-catalog.v4';

    /** @var array<int, array{id:int,slug:string,label:string,active:bool}>|null */
    private ?array $allOptions = null;

    /** @var array<int, array{id:int,slug:string,label:string}> */
    private array $activeOptions = [];

    /** @var array<int, array{id:int,slug:string,label:string}> */
    private array $developerOptions = [];

    /** @var array<int, array{id:int,slug:string,label:string,active:bool}> */
    private array $optionsById = [];

    /** @var array<string, array{id:int,slug:string,label:string,active:bool}> */
    private array $activeOptionsBySlug = [];

    /** @var array<string, array{id:int,slug:string,label:string,active:bool}> */
    private array $developerOptionsBySlug = [];

    /**
     * @return array<int, array{id:int|null,slug:string,label:string}>
     */
    public function options(bool $activeOnly = true, bool $includeInactive = false): array
    {
        $this->load();

        if ($activeOnly) {
            return $includeInactive ? $this->developerOptions : $this->activeOptions;
        }

        return array_values(array_map(
            $this->publicOption(...),
            array_filter(
                $this->allOptions ?? [],
                static fn (array $option): bool => $includeInactive || $option['active'],
            ),
        ));
    }

    public function idForSlug(string $slug, bool $includeInactive = false): ?int
    {
        $this->load();
        $slug = Str::slug(trim($slug));

        $options = $includeInactive ? $this->developerOptionsBySlug : $this->activeOptionsBySlug;

        return $options[$slug]['id'] ?? null;
    }

    public function slugForId(int|string|null $branchId): string
    {
        $this->load();
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $branchId !== false ? ($this->optionsById[$branchId]['slug'] ?? '') : '';
    }

    public function labelForId(int|string|null $branchId): string
    {
        $this->load();
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $branchId !== false ? ($this->optionsById[$branchId]['label'] ?? 'Tanpa cabang') : 'Tanpa cabang';
    }

    public function isActiveId(int|string|null $branchId, bool $includeInactive = false): bool
    {
        $this->load();
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($branchId === false || ! isset($this->optionsById[$branchId])) {
            return false;
        }

        return $includeInactive || (bool) ($this->optionsById[$branchId]['active'] ?? false);
    }

    public function isInactiveId(int|string|null $branchId): bool
    {
        $this->load();
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $branchId !== false
            && isset($this->optionsById[$branchId])
            && ! (bool) ($this->optionsById[$branchId]['active'] ?? false);
    }

    public function normalizeSlug(string $slug, bool $includeInactive = false): string
    {
        $this->load();
        $slug = Str::slug(trim($slug));
        $options = $includeInactive ? $this->developerOptionsBySlug : $this->activeOptionsBySlug;

        return isset($options[$slug]) ? $slug : '';
    }

    public function clearCache(): void
    {
        Cache::store($this->cacheStore())->forget(self::CACHE_KEY);
        $this->allOptions = null;
        $this->activeOptions = [];
        $this->developerOptions = [];
        $this->optionsById = [];
        $this->activeOptionsBySlug = [];
        $this->developerOptionsBySlug = [];
    }

    private function load(): void
    {
        if ($this->allOptions !== null) {
            return;
        }

        try {
            $options = Cache::store($this->cacheStore())->rememberForever(
                self::CACHE_KEY,
                fn (): array => $this->databaseOptions(),
            );
        } catch (Throwable) {
            $options = self::fallbackOptionsWithState();
        }

        if (! is_array($options) || $options === []) {
            $options = self::fallbackOptionsWithState();
        }

        $this->allOptions = array_values($options);
        foreach ($this->allOptions as $option) {
            $this->optionsById[$option['id']] = $option;
            $publicOption = $this->publicOption($option);
            $this->developerOptions[] = $publicOption;
            $this->developerOptionsBySlug[$option['slug']] = $option;
            if ($option['active']) {
                $this->activeOptions[] = $publicOption;
                $this->activeOptionsBySlug[$option['slug']] = $option;
            }
        }
    }

    /** @return array<int, array{id:int,slug:string,label:string,active:bool}> */
    private function databaseOptions(): array
    {
        return Branch::query()
            ->orderBy('label')
            ->get(['id', 'label', 'is_active'])
            ->map(static function (Branch $branch): array {
                $label = trim((string) $branch->label);

                return [
                    'id' => (int) $branch->id,
                    'slug' => Str::slug($label),
                    'label' => $label,
                    'active' => (bool) $branch->is_active,
                ];
            })
            ->filter(static fn (array $option): bool => $option['slug'] !== '' && $option['slug'] !== 'pusat')
            ->values()
            ->all();
    }

    /**
     * @param  array{id:int,slug:string,label:string,active:bool}  $option
     * @return array{id:int,slug:string,label:string}
     */
    private function publicOption(array $option): array
    {
        return [
            'id' => $option['id'],
            'slug' => $option['slug'],
            'label' => $option['label'],
        ];
    }

    /** @return array<int, array{id:int,slug:string,label:string}> */
    public function activeOptionsById(): array
    {
        $this->load();

        return array_column($this->activeOptions, null, 'id');
    }

    /** @return array<int, array{id:int,slug:string,label:string}> */
    public function developerOptionsById(): array
    {
        $this->load();

        return array_column($this->developerOptions, null, 'id');
    }

    private function cacheStore(): string
    {
        return app()->environment('testing') ? 'array' : 'file';
    }

    /** @return array<int, array{id:int,slug:string,label:string,active:bool}> */
    private static function fallbackOptionsWithState(): array
    {
        return array_map(
            static fn (array $option): array => [...$option, 'active' => true],
            self::fallbackOptions(),
        );
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
