<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscipleshipPeople\ExportDiscipleshipPeopleRequest;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleExportService;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleListData;
use App\Support\RuntimeBootstrap;
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

    public function export(
        ExportDiscipleshipPeopleRequest $request,
        DiscipleshipPeopleExportService $exporter,
    ): BinaryFileResponse|RedirectResponse {
        RuntimeBootstrap::boot($request);

        return $exporter->export($request);
    }
}
