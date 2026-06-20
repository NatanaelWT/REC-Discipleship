<?php

namespace App\Http\Middleware;

use App\Models\MskParticipant;
use App\Services\Branches\BranchCatalog;
use App\Services\Discipleship\DiscipleshipReadCache;
use Closure;
use Illuminate\Http\Request;
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
        if ($request->isMethodSafe() || $response->getStatusCode() >= 500) {
            return $response;
        }

        $routeName = (string) optional($request->route())->getName();
        if (str_starts_with($routeName, 'discipleship.')
            || str_starts_with($routeName, 'public.dg.')) {
            $branchIds = $this->branchIds($request);
            if ($branchIds === []) {
                $this->cache->invalidate();
            } else {
                $this->cache->invalidateBranches($branchIds);
            }
        }

        return $response;
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
        if ($participant instanceof MskParticipant && (int) $participant->branch_id > 0) {
            $ids[(int) $participant->branch_id] = (int) $participant->branch_id;
        }

        $branchSlug = trim((string) ($request->route('branch') ?? $request->input('branch', '')));
        if ($branchSlug !== '') {
            $branchId = $this->branches->idForSlug($branchSlug);
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
