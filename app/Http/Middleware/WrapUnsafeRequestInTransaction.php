<?php

namespace App\Http\Middleware;

use App\Services\Mutation\MutationLifecycle;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WrapUnsafeRequestInTransaction
{
    public function __construct(private readonly MutationLifecycle $lifecycle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $startingTransactionLevel = DB::transactionLevel();
        $wasAuthenticated = Auth::check();
        $this->lifecycle->begin();

        try {
            DB::beginTransaction();
            $response = $next($request);

            if ($response->getStatusCode() >= 500) {
                $this->rollBackTo($startingTransactionLevel);
                $this->lifecycle->runRollbackCallbacks();
                $this->restoreAuthentication($wasAuthenticated);

                return $response;
            }

            DB::commit();
            $this->lifecycle->runCommitCallbacks();

            return $response;
        } catch (Throwable $exception) {
            $this->rollBackTo($startingTransactionLevel);
            $this->lifecycle->runRollbackCallbacks();
            $this->restoreAuthentication($wasAuthenticated);

            throw $exception;
        } finally {
            $this->lifecycle->clear();
        }
    }

    private function rollBackTo(int $startingTransactionLevel): void
    {
        while (DB::transactionLevel() > $startingTransactionLevel) {
            DB::rollBack();
        }
    }

    private function restoreAuthentication(bool $wasAuthenticated): void
    {
        if (! $wasAuthenticated && Auth::check()) {
            Auth::logout();
        }
    }
}
