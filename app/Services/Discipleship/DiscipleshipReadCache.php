<?php

namespace App\Services\Discipleship;

use App\Services\Branches\BranchCatalog;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class DiscipleshipReadCache
{
    private const GLOBAL_VERSION_KEY = 'rec.discipleship-read.version.global';

    private const BRANCH_VERSION_PREFIX = 'rec.discipleship-read.version.branch.';

    private int $hits = 0;

    private int $misses = 0;

    /** @var array<string, string> */
    private array $versionMemo = [];

    public function __construct(private readonly BranchCatalog $branches) {}

    public function remember(string $segment, array $scope, Closure $callback): mixed
    {
        sort($scope);
        $store = Cache::store($this->cacheStore());
        $versions = [$this->version($store, self::GLOBAL_VERSION_KEY)];
        foreach ($this->branchIds($scope) as $branchId) {
            $versions[] = $this->version($store, self::BRANCH_VERSION_PREFIX.$branchId);
        }
        $key = 'rec.discipleship-read.v3.'.sha1(implode('|', $versions)).'.'.$segment.'.'.sha1(json_encode($scope) ?: '[]');

        if ($store->has($key)) {
            $this->hits++;
        } else {
            $this->misses++;
        }

        return $store->flexible($key, [300, 1800], $callback);
    }

    public function invalidate(): void
    {
        $version = (string) hrtime(true);
        Cache::store($this->cacheStore())->forever(self::GLOBAL_VERSION_KEY, $version);
        $this->versionMemo[self::GLOBAL_VERSION_KEY] = $version;
    }

    /** @param array<int, int|string> $branchIds */
    public function invalidateBranches(array $branchIds): void
    {
        $store = Cache::store($this->cacheStore());
        foreach ($this->branchIds($branchIds) as $branchId) {
            $key = self::BRANCH_VERSION_PREFIX.$branchId;
            $version = (string) hrtime(true);
            $store->forever($key, $version);
            $this->versionMemo[$key] = $version;
        }
    }

    /** @return array{hits:int,misses:int} */
    public function metrics(): array
    {
        return ['hits' => $this->hits, 'misses' => $this->misses];
    }

    private function cacheStore(): string
    {
        return (string) config('cache.discipleship_store', config('cache.default', 'file'));
    }

    private function version(Repository $store, string $key): string
    {
        if (! array_key_exists($key, $this->versionMemo)) {
            $this->versionMemo[$key] = (string) $store->get($key, '1');
        }

        return $this->versionMemo[$key];
    }

    /** @return array<int, int> */
    private function branchIds(array $scope): array
    {
        $ids = [];
        foreach ($scope as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $branchId = (int) $value;
                if ($branchId > 0) {
                    $ids[$branchId] = $branchId;
                }
            } elseif (is_string($value)) {
                $branchId = $this->branches->idForSlug($value, true);
                if ($branchId !== null) {
                    $ids[$branchId] = $branchId;
                }
            }
        }

        ksort($ids);

        return array_values($ids);
    }
}
