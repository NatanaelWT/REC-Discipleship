<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscipleshipPeople\ExportDiscipleshipPeopleRequest;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleExportService;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleListData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PeopleListController extends Controller
{
    public function index(Request $request, DiscipleshipPeopleListData $peopleListData): View
    {
        RuntimeBootstrap::boot($request);

        return view('discipleship.people-list.index', $peopleListData->forCurrentContext($request));
    }

    public function rows(Request $request, DiscipleshipPeopleListData $peopleListData): JsonResponse
    {
        RuntimeBootstrap::boot($request);

        $data = $peopleListData->paginatedRowsForCurrentContext($request);

        return response()->json([
            'html' => view('discipleship.people-list.partials.rows', ['people' => $data['people']])->render(),
            'has_more' => (bool) ($data['hasMorePeopleRows'] ?? false),
            'next_page' => $data['nextPeoplePage'] ?? null,
            'stats' => [
                'total' => (int) ($data['totalPeopleRows'] ?? 0),
                'dg1' => (int) ($data['peopleInDg1Count'] ?? 0),
                'dg2' => (int) ($data['peopleInDg2Count'] ?? 0),
                'dg3' => (int) ($data['peopleInDg3Count'] ?? 0),
            ],
            'empty_message' => (string) ($data['peopleEmptyMessage'] ?? 'Peserta tidak ditemukan.'),
        ]);
    }

    public function export(
        ExportDiscipleshipPeopleRequest $request,
        DiscipleshipPeopleExportService $exporter,
    ): BinaryFileResponse|RedirectResponse {
        RuntimeBootstrap::boot($request);

        return $exporter->export($request);
    }
}
