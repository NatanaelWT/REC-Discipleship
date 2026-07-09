<?php

namespace App\Services\Branches;

use App\Models\Branch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class BranchCatalog
{
    private const CACHE_KEY = 'rec.branch-catalog.v3';

    /** @var array<int, array{id:int,slug:string,label:string,active:bool,developer_only:bool}>|null */
    private ?array $allOptions = null;

    /** @var array<int, array{id:int,slug:string,label:string}> */
    private array $activeOptions = [];

    /** @var array<int, array{id:int,slug:string,label:string}> */
    private array $developerOptions = [];

    /** @var array<int, array{id:int,slug:string,label:string,active:bool,developer_only:bool}> */
    private array $optionsById = [];

    /** @var array<string, array{id:int,slug:string,label:string,active:bool,developer_only:bool}> */
    private array $activeOptionsBySlug = [];

    /** @var array<string, array{id:int,slug:string,label:string,active:bool,developer_only:bool}> */
    private array $developerOptionsBySlug = [];

    /**
     * @return array<int, array{id:int|null,slug:string,label:string}>
     */
    public function options(bool $activeOnly = true, bool $includeDeveloperOnly = false): array
    {
        $this->load();

        if ($activeOnly) {
            return $includeDeveloperOnly ? $this->developerOptions : $this->activeOptions;
        }

        return array_values(array_map(
            $this->publicOption(...),
            array_filter(
                $this->allOptions ?? [],
                static fn (array $option): bool => $includeDeveloperOnly || ! $option['developer_only'],
            ),
        ));
    }

    public function idForSlug(string $slug, bool $includeDeveloperOnly = false): ?int
    {
        $this->load();
        $slug = Str::slug(trim($slug));

        $options = $includeDeveloperOnly ? $this->developerOptionsBySlug : $this->activeOptionsBySlug;

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

    public function isActiveId(int|string|null $branchId, bool $includeDeveloperOnly = false): bool
    {
        $this->load();
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($branchId === false || ! ($this->optionsById[$branchId]['active'] ?? false)) {
            return false;
        }

        return $includeDeveloperOnly || ! ($this->optionsById[$branchId]['developer_only'] ?? false);
    }

    public function isDeveloperOnlyId(int|string|null $branchId): bool
    {
        $this->load();
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $branchId !== false && (bool) ($this->optionsById[$branchId]['developer_only'] ?? false);
    }

    public function normalizeSlug(string $slug, bool $includeDeveloperOnly = false): string
    {
        $this->load();
        $slug = Str::slug(trim($slug));
        $options = $includeDeveloperOnly ? $this->developerOptionsBySlug : $this->activeOptionsBySlug;

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
            if (! $option['active']) {
                continue;
            }

            $publicOption = $this->publicOption($option);
            $this->developerOptions[] = $publicOption;
            $this->developerOptionsBySlug[$option['slug']] = $option;
            if (! $option['developer_only']) {
                $this->activeOptions[] = $publicOption;
                $this->activeOptionsBySlug[$option['slug']] = $option;
            }
        }
    }

    /** @return array<int, array{id:int,slug:string,label:string,active:bool,developer_only:bool}> */
    private function databaseOptions(): array
    {
        $columns = ['id', 'label', 'is_active'];
        $hasDeveloperOnly = Schema::hasColumn('cabang', 'is_developer_only');
        if ($hasDeveloperOnly) {
            $columns[] = 'is_developer_only';
        }

        return Branch::query()
            ->orderBy('label')
            ->get($columns)
            ->map(static function (Branch $branch) use ($hasDeveloperOnly): array {
                $label = trim((string) $branch->label);

                return [
                    'id' => (int) $branch->id,
                    'slug' => Str::slug($label),
                    'label' => $label,
                    'active' => (bool) $branch->is_active,
                    'developer_only' => $hasDeveloperOnly && (bool) ($branch->is_developer_only ?? false),
                ];
            })
            ->filter(static fn (array $option): bool => $option['slug'] !== '' && $option['slug'] !== 'pusat')
            ->values()
            ->all();
    }

    /**
     * @param  array{id:int,slug:string,label:string,active:bool,developer_only:bool}  $option
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

    /** @return array<int, array{id:int,slug:string,label:string,active:bool,developer_only:bool}> */
    private static function fallbackOptionsWithState(): array
    {
        return array_map(
            static fn (array $option): array => [...$option, 'active' => true, 'developer_only' => false],
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
