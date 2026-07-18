<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscipleshipPeople\ExportDiscipleshipPeopleRequest;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleExportService;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleListData;
use App\Services\DiscipleshipPeopleTree\PeopleTreeDetailData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PeopleListController extends Controller
{
    public function index(
        Request $request,
        DiscipleshipPeopleListData $peopleListData,
        CurrentDiscipleshipScope $scope,
    ): View {
        $pageTitle = 'Daftar Anggota DG';
        $pageData = [
            ...$peopleListData->forCurrentContext($request),
            'pageTitle' => $pageTitle,
        ];

        if ($request->header('X-Discipleship-Fragment') === 'tab') {
            return view('discipleship.people-list.index', $pageData);
        }

        return view('discipleship.workspace.index', [
            ...$pageData,
            'activeTab' => 'people',
            'currentPage' => 'people_list',
            'panelView' => 'discipleship.people-list.index',
            'selectedBranchLabel' => $scope->selectedLabel(),
            'tabBranchId' => $this->tabBranchId($request, $scope),
        ]);
    }

    public function rows(Request $request, DiscipleshipPeopleListData $peopleListData): JsonResponse
    {
        $data = $peopleListData->paginatedRowsForCurrentContext($request);

        return response()->json([
            'html' => view('discipleship.people-list.partials.rows', ['people' => $data['people']])->render(),
            'stats' => [
                'total' => (int) ($data['totalPeopleRows'] ?? 0),
                'dg1' => (int) ($data['peopleInDg1Count'] ?? 0),
                'dg2' => (int) ($data['peopleInDg2Count'] ?? 0),
                'dg3' => (int) ($data['peopleInDg3Count'] ?? 0),
            ],
            'has_more' => (bool) ($data['hasMorePeopleRows'] ?? false),
            'next_cursor' => $data['nextPeopleCursor'] ?? null,
            'empty' => count($data['people'] ?? []) === 0,
            'empty_message' => (string) ($data['peopleEmptyMessage'] ?? 'Peserta tidak ditemukan.'),
        ]);
    }

    public function export(
        ExportDiscipleshipPeopleRequest $request,
        DiscipleshipPeopleExportService $exporter,
    ): BinaryFileResponse|RedirectResponse {
        return $exporter->export($request);
    }

    public function detail(int $person, PeopleTreeDetailData $detailData): JsonResponse
    {
        $detail = $detailData->person($person);
        if ($detail === null) {
            abort(404);
        }

        return response()->json($detail)->header('Cache-Control', 'private, no-store');
    }

    private function tabBranchId(Request $request, CurrentDiscipleshipScope $scope): int|string|null
    {
        if (! $request->query->has('branch_id')) {
            return null;
        }

        return $scope->includesAllBranches()
            ? 'all'
            : $scope->selectedBranchId();
    }
}
