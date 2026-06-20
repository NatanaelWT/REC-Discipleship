<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscipleshipDashboard\UpdateDashboardMskSessionsRequest;
use App\Services\DiscipleshipDashboard\DashboardMskSessionUpdater;
use App\Services\DiscipleshipDashboard\DashboardPageData;
use App\Services\DiscipleshipDashboard\DashboardSectionData;
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
        $participantId = $request->participantId();
        $redirectParams = [];
        if ($participantId > 0) {
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

    public function section(Request $request, string $section, DashboardSectionData $sections): View
    {
        RuntimeBootstrap::boot($request);

        return match ($section) {
            'incomplete-msk' => view('discipleship.dashboard.sections.incomplete-msk', $sections->incompleteMsk($request)),
            'overdue-groups' => view('discipleship.dashboard.sections.overdue-groups', $sections->overdueGroups($request)),
            'branch-breakdown' => view('discipleship.dashboard.sections.branch-breakdown', $sections->branchBreakdown()),
            default => abort(404),
        };
    }

    private function guardPageAccess(Request $request): ?RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        return null;
    }
}
