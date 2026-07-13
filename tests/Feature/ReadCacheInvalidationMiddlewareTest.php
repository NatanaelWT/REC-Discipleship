<?php

namespace Tests\Feature;

use App\Services\Discipleship\DiscipleshipReadCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReadCacheInvalidationMiddlewareTest extends TestCase
{
    public function test_validation_failure_does_not_invalidate_discipleship_cache(): void
    {
        $cache = $this->mock(DiscipleshipReadCache::class);
        $cache->shouldReceive('metrics')->andReturn(['hits' => 0, 'misses' => 0]);
        $cache->shouldNotReceive('invalidate');
        $cache->shouldNotReceive('invalidateBranches');

        Route::middleware('web')
            ->post('/_tests/cache/validation', static fn () => redirect('/')->withErrors([
                'name' => 'The name field is required.',
            ]))
            ->name('discipleship.test-validation');

        $this->post('/_tests/cache/validation')->assertRedirect('/');
    }

    public function test_explicit_no_op_does_not_invalidate_discipleship_cache(): void
    {
        $cache = $this->mock(DiscipleshipReadCache::class);
        $cache->shouldReceive('metrics')->andReturn(['hits' => 0, 'misses' => 0]);
        $cache->shouldNotReceive('invalidate');
        $cache->shouldNotReceive('invalidateBranches');

        Route::middleware('web')
            ->post('/_tests/cache/no-op', static function (Request $request) {
                $request->attributes->set('discipleship.no_mutation', true);

                return response()->noContent();
            })
            ->name('discipleship.test-no-op');

        $this->post('/_tests/cache/no-op')->assertNoContent();
    }

    public function test_post_export_does_not_invalidate_discipleship_cache(): void
    {
        $cache = $this->mock(DiscipleshipReadCache::class);
        $cache->shouldReceive('metrics')->andReturn(['hits' => 0, 'misses' => 0]);
        $cache->shouldNotReceive('invalidate');
        $cache->shouldNotReceive('invalidateBranches');

        Route::middleware('web')
            ->post('/_tests/cache/export', static fn () => response('export'))
            ->name('discipleship.test.export');

        $this->post('/_tests/cache/export')->assertOk();
    }

    public function test_successful_domain_mutation_invalidates_only_the_requested_branch(): void
    {
        $cache = $this->mock(DiscipleshipReadCache::class);
        $cache->shouldReceive('metrics')->andReturn(['hits' => 0, 'misses' => 0]);
        $cache->shouldNotReceive('invalidate');
        $cache->shouldReceive('invalidateBranches')->once()->with([1]);

        Route::middleware('web')
            ->post('/_tests/cache/success', static fn () => response()->noContent())
            ->name('discipleship.test-success');

        $this->post('/_tests/cache/success', ['branch_id' => 1])->assertNoContent();
    }
}
