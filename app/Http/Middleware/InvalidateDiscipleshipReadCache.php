<?php

namespace App\Http\Middleware;

use App\Models\Person;
use App\Services\Branches\BranchCatalog;
use App\Services\Discipleship\DiscipleshipReadCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

class InvalidateDiscipleshipReadCache
{
    public function __construct(
        private readonly DiscipleshipReadCache $cache,
        private readonly BranchCatalog $branches,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $this->shouldInvalidate($request, $response)) {
            return $response;
        }

        $routeName = (string) optional($request->route())->getName();
        if (str_starts_with($routeName, 'discipleship.')
            || str_starts_with($routeName, 'public.dg.')
            || str_starts_with($routeName, 'public.member-feedback.')) {
            $branchIds = $this->branchIds($request);
            $invalidate = function () use ($branchIds): void {
                if ($branchIds === []) {
                    $this->cache->invalidate();

                    return;
                }

                $this->cache->invalidateBranches($branchIds);
            };

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($invalidate);
            } else {
                $invalidate();
            }
        }

        return $response;
    }

    private function shouldInvalidate(Request $request, Response $response): bool
    {
        if ($request->isMethodSafe() || $response->getStatusCode() >= 400) {
            return false;
        }
        $routeName = (string) optional($request->route())->getName();
        if (str_ends_with($routeName, '.export') || str_ends_with($routeName, '.export-dot')) {
            return false;
        }
        if ($this->hasValidationErrors($request, $response)
            || $request->attributes->get('discipleship.no_mutation') === true) {
            return false;
        }

        $location = trim((string) $response->headers->get('Location'));

        return $location === ''
            || preg_match('/(?:^|[?&])(error|material_error)=/', $location) !== 1;
    }

    private function hasValidationErrors(Request $request, Response $response): bool
    {
        if (! $response->isRedirect() || ! $request->hasSession()) {
            return false;
        }

        $errors = $request->session()->get('errors');

        return $errors instanceof ViewErrorBag && $errors->any();
    }

    /** @return array<int, int> */
    private function branchIds(Request $request): array
    {
        $ids = [];
        $input = $request->input('branch_id');
        if (is_numeric($input) && (int) $input > 0) {
            $ids[(int) $input] = (int) $input;
        }

        $participant = $request->route('participant');
        if ($participant instanceof Person && (int) $participant->branch_id > 0) {
            $ids[(int) $participant->branch_id] = (int) $participant->branch_id;
        }

        $branchSlug = trim((string) (
            $request->route('branch')
            ?? $request->input('branch', $request->input('public_cabang', ''))
        ));
        if ($branchSlug !== '') {
            $branchId = $this->branches->idForSlug($branchSlug, true);
            if ($branchId !== null) {
                $ids[$branchId] = $branchId;
            }
        }

        if (function_exists('current_user_branch_id')) {
            $branchId = current_user_branch_id();
            if ($branchId !== null) {
                $ids[$branchId] = $branchId;
            }
        }

        return array_values($ids);
    }
}
