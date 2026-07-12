<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\MemberFeedbackJournals\MemberFeedbackRecapPageData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberFeedbackRecapController extends Controller
{
    public function index(
        Request $request,
        MemberFeedbackRecapPageData $pageData,
        CurrentDiscipleshipScope $scope,
    ): View {
        RuntimeBootstrap::boot($request);

        $pageTitle = 'Jurnal Umpan Balik';
        $data = [
            ...$pageData->forCurrentContext($request),
            'pageTitle' => $pageTitle,
        ];

        if ($request->header('X-Discipleship-Fragment') === 'tab') {
            return view('discipleship.member-feedback.panel', $data);
        }

        return view('discipleship.journals.workspace', [
            ...$data,
            'activeTab' => 'feedback',
            'currentPage' => 'member_feedback_recap',
            'panelView' => 'discipleship.member-feedback.panel',
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
