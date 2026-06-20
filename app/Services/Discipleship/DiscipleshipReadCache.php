<?php

namespace App\Services\Discipleship;

use App\Services\Branches\BranchCatalog;
use Closure;
use Illuminate\Support\Facades\Cache;

class DiscipleshipReadCache
{
    private const GLOBAL_VERSION_KEY = 'rec.discipleship-read.version.global';

    private const BRANCH_VERSION_PREFIX = 'rec.discipleship-read.version.branch.';

    private int $hits = 0;

    private int $misses = 0;

    public function __construct(private readonly BranchCatalog $branches) {}

    public function remember(string $segment, array $scope, Closure $callback): mixed
    {
        sort($scope);
        $store = Cache::store($this->cacheStore());
        $versions = [(string) $store->get(self::GLOBAL_VERSION_KEY, '1')];
        foreach ($this->branchIds($scope) as $branchId) {
            $versions[] = (string) $store->get(self::BRANCH_VERSION_PREFIX.$branchId, '1');
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
        Cache::store($this->cacheStore())->forever(self::GLOBAL_VERSION_KEY, (string) hrtime(true));
    }

    /** @param array<int, int|string> $branchIds */
    public function invalidateBranches(array $branchIds): void
    {
        $store = Cache::store($this->cacheStore());
        foreach ($this->branchIds($branchIds) as $branchId) {
            $store->forever(self::BRANCH_VERSION_PREFIX.$branchId, (string) hrtime(true));
        }
    }

    /** @return array{hits:int,misses:int} */
    public function metrics(): array
    {
        return ['hits' => $this->hits, 'misses' => $this->misses];
    }

    private function cacheStore(): string
    {
        return app()->environment('testing') ? 'array' : 'file';
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
                $branchId = $this->branches->idForSlug($value);
                if ($branchId !== null) {
                    $ids[$branchId] = $branchId;
                }
            }
        }

        ksort($ids);

        return array_values($ids);
    }
}
