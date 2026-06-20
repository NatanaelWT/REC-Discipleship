<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\Developer\DeveloperBranchService;
use App\Services\Developer\DeveloperDiagnosticsService;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    public function index(
        Request $request,
        DeveloperDiagnosticsService $diagnostics,
        DeveloperBranchService $branches,
    ): RedirectResponse|View {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('developer.dashboard', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_dashboard',
            'activeBranch' => current_user_branch(),
            'activeBranchId' => current_user_branch_id(),
            'branchOptions' => $branches->options(),
            'diagnostics' => $diagnostics->summary(),
            'branchChanged' => $request->query->has('branch_changed'),
            'branchError' => trim((string) $request->query('branch_error', '')),
        ]);
    }

    public function switchBranch(Request $request, DeveloperBranchService $branches): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $branchId = $branches->normalizeAllowedId($request->input('branch_id'));
        if ($branchId === null) {
            return redirect()->route('developer.dashboard', ['branch_error' => 'branch_invalid']);
        }

        $request->session()->put('developer_branch_id', $branchId);
        $request->session()->forget('developer_branch');

        return redirect()->route('developer.dashboard', ['branch_changed' => 1]);
    }

    private function guard(Request $request): ?RedirectResponse
    {
        RuntimeBootstrap::boot($request);
        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }
        if (! can_manage_users()) {
            abort(403, 'Akses developer diperlukan.');
        }

        return null;
    }
}
