<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\DgMeetingReports\DgMeetingReportRecapPageData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MeetingReportRecapController extends Controller
{
    public function index(
        Request $request,
        DgMeetingReportRecapPageData $pageData,
        CurrentDiscipleshipScope $scope,
    ): Response|View {
        RuntimeBootstrap::boot($request);

        $pageTitle = 'Jurnal Temu DG';
        $data = [
            ...$pageData->forCurrentContext($request),
            'pageTitle' => $pageTitle,
            'renderAsTabPanel' => true,
        ];

        if ($request->header('X-Discipleship-Fragment') === 'tab') {
            return response(view('discipleship.meeting-reports.recap', $data)->render());
        }

        return view('discipleship.journals.workspace', [
            ...$data,
            'activeTab' => 'meeting',
            'currentPage' => 'dg_reports_recap',
            'panelView' => 'discipleship.meeting-reports.recap',
            'tabBranchId' => $this->tabBranchId($request, $scope),
        ]);
    }

    private function tabBranchId(Request $request, CurrentDiscipleshipScope $scope): int|string|null
    {
        if (! $request->query->has('branch_id') && ! $request->query->has('rekap_cabang')) {
            return null;
        }

        return $scope->includesAllBranches()
            ? 'all'
            : $scope->selectedBranchId();
    }
}
