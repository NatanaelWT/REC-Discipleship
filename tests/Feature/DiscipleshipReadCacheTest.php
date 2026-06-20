<?php

namespace Tests\Feature;

use App\Services\Discipleship\DiscipleshipReadCache;
use Tests\TestCase;

class DiscipleshipReadCacheTest extends TestCase
{
    public function test_invalidating_one_branch_keeps_other_branch_cache_warm(): void
    {
        $cache = app(DiscipleshipReadCache::class);
        $branchOneBuilds = 0;
        $branchTwoBuilds = 0;

        $cache->remember('scope-test', [1], function () use (&$branchOneBuilds): int {
            return ++$branchOneBuilds;
        });
        $cache->remember('scope-test', [2], function () use (&$branchTwoBuilds): int {
            return ++$branchTwoBuilds;
        });
        $cache->invalidateBranches([1]);

        $cache->remember('scope-test', [1], function () use (&$branchOneBuilds): int {
            return ++$branchOneBuilds;
        });
        $cache->remember('scope-test', [2], function () use (&$branchTwoBuilds): int {
            return ++$branchTwoBuilds;
        });

        $this->assertSame(2, $branchOneBuilds);
        $this->assertSame(1, $branchTwoBuilds);
    }
}
