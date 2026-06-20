<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
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
    ): RedirectResponse|View {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $request->session()->forget(['developer_branch', 'developer_branch_id']);

        return view('developer.dashboard', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_dashboard',
            'diagnostics' => $diagnostics->summary(),
        ]);
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
