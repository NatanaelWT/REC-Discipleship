<?php

namespace Tests\Feature;

use App\Http\Middleware\WrapUnsafeRequestInTransaction;
use App\Services\Mutation\MutationLifecycle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MutationTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('mutation_test_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });

        Storage::fake('local');
    }

    public function test_successful_mutation_commits_database_and_runs_file_callbacks_after_commit(): void
    {
        Storage::disk('local')->put('mutation/old.txt', 'old');

        Route::middleware('web')->post('/_tests/mutation/commit', function (MutationLifecycle $lifecycle) {
            DB::table('mutation_test_rows')->insert(['value' => 'committed']);
            Storage::disk('local')->put('mutation/new.txt', 'new');

            $lifecycle->onRollback(
                static fn () => Storage::disk('local')->delete('mutation/new.txt'),
            );
            $lifecycle->onCommit(
                static fn () => Storage::disk('local')->delete('mutation/old.txt'),
            );

            return response()->noContent();
        });

        $this->post('/_tests/mutation/commit')
            ->assertNoContent()
            ->assertHeaderMissing('X-Activity-Request-Id');

        $this->assertDatabaseHas('mutation_test_rows', ['value' => 'committed']);
        Storage::disk('local')->assertExists('mutation/new.txt');
        Storage::disk('local')->assertMissing('mutation/old.txt');
    }

    public function test_server_error_response_rolls_back_database_and_new_file_but_preserves_old_file(): void
    {
        Storage::disk('local')->put('mutation/old.txt', 'old');

        Route::middleware('web')->post('/_tests/mutation/server-error', function (MutationLifecycle $lifecycle) {
            DB::table('mutation_test_rows')->insert(['value' => 'rolled-back']);
            Storage::disk('local')->put('mutation/new.txt', 'new');

            $lifecycle->onRollback(
                static fn () => Storage::disk('local')->delete('mutation/new.txt'),
            );
            $lifecycle->onCommit(
                static fn () => Storage::disk('local')->delete('mutation/old.txt'),
            );

            return response('failed', 500);
        });

        $this->post('/_tests/mutation/server-error')->assertStatus(500);

        $this->assertDatabaseMissing('mutation_test_rows', ['value' => 'rolled-back']);
        Storage::disk('local')->assertMissing('mutation/new.txt');
        Storage::disk('local')->assertExists('mutation/old.txt');
    }

    public function test_exception_rolls_back_database_and_runs_cleanup_callbacks(): void
    {
        Route::middleware('web')->post('/_tests/mutation/exception', function (MutationLifecycle $lifecycle): never {
            DB::table('mutation_test_rows')->insert(['value' => 'exception']);
            Storage::disk('local')->put('mutation/exception.txt', 'new');
            $lifecycle->onRollback(
                static fn () => Storage::disk('local')->delete('mutation/exception.txt'),
            );

            throw new RuntimeException('Expected transaction failure.');
        });

        $this->withoutExceptionHandling();

        try {
            $this->post('/_tests/mutation/exception');
            $this->fail('The route exception should have been rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Expected transaction failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('mutation_test_rows', ['value' => 'exception']);
        Storage::disk('local')->assertMissing('mutation/exception.txt');
    }

    public function test_routes_that_do_not_need_the_request_wide_transaction_explicitly_exclude_it(): void
    {
        foreach ([
            'auth.logout',
            'developer.access.return',
            'developer.users.access',
            'public.difficult-question.lookup',
            'discipleship.tree.export-dot',
            'discipleship.msk-classes.import',
            'discipleship.msk-classes.export',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains(
                WrapUnsafeRequestInTransaction::class,
                $route->excludedMiddleware(),
                "Route [{$routeName}] must explicitly exclude the request-wide database transaction.",
            );
        }

        foreach ([
            'auth.login.store',
            'settings.update',
            'materials.upload',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertNotContains(
                WrapUnsafeRequestInTransaction::class,
                $route->excludedMiddleware(),
                "Mutating route [{$routeName}] must retain the transaction middleware.",
            );
        }
    }
}
