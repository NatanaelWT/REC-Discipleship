<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscipleshipDashboard\UpdateDashboardMskSessionsRequest;
use App\Services\DiscipleshipDashboard\DashboardMskSessionUpdater;
use App\Services\DiscipleshipDashboard\DashboardPageData;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardPageData $pageData): RedirectResponse|View
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return view('discipleship.dashboard.index', $pageData->forCurrentContext($request));
    }

    public function updateMskSessions(
        UpdateDashboardMskSessionsRequest $request,
        DashboardMskSessionUpdater $updater,
    ): RedirectResponse {
        $participantId = $request->participantPublicId();
        $redirectParams = [];
        if ($participantId !== '') {
            $redirectParams['edit_msk_sessions'] = $participantId;
        }

        $result = $updater->update($participantId, $request->sessionNumbers());
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.dashboard', $redirectParams + ['error' => 'invalid_msk_participant']);
        }

        $redirectParams = ['msk_session_saved' => 1];
        if ($result['auto_converted']) {
            $redirectParams['converted'] = 1;
        }

        return redirect()->route('discipleship.dashboard', $redirectParams);
    }

    private function guardPageAccess(Request $request): ?RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        if (trim((string) $request->input('action', '')) === 'logout') {
            destroy_current_session();

            return redirect()->route('home');
        }

        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }

        if (! branch_can_access_page(current_user_branch(), 'discipleship_dashboard')) {
            return redirect(AppPageRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied']));
        }

        return null;
    }
}
