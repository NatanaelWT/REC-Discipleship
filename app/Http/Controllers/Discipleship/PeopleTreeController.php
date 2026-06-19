<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscipleshipPeopleTree\CompletePeopleTreeGroupRequest;
use App\Http\Requests\DiscipleshipPeopleTree\DeletePeopleTreePersonRequest;
use App\Http\Requests\DiscipleshipPeopleTree\ExportPeopleTreeDotRequest;
use App\Http\Requests\DiscipleshipPeopleTree\LeavePeopleTreeGroupRequest;
use App\Http\Requests\DiscipleshipPeopleTree\PeopleTreeActionRequest;
use App\Http\Requests\DiscipleshipPeopleTree\ReactivatePeopleTreeGroupRequest;
use App\Http\Requests\DiscipleshipPeopleTree\SavePeopleTreeGroupRequest;
use App\Http\Requests\DiscipleshipPeopleTree\SavePeopleTreePersonRequest;
use App\Services\DiscipleshipPeopleTree\PeopleTreePageData;
use App\Services\DiscipleshipPeopleTree\PeopleTreeWriter;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PeopleTreeController extends Controller
{
    public function index(Request $request, PeopleTreePageData $pageData): RedirectResponse|View
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return view('discipleship.people-tree.index', $pageData->forCurrentContext($request));
    }

    public function treeV2(Request $request): RedirectResponse
    {
        return redirect()->route('discipleship.tree', $request->query());
    }

    public function handleFormAction(PeopleTreeActionRequest $request, PeopleTreeWriter $writer): RedirectResponse
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return $writer->handleFormAction($request);
    }

    public function savePerson(SavePeopleTreePersonRequest $request, PeopleTreeWriter $writer): RedirectResponse
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return $writer->savePerson($request);
    }

    public function deletePerson(DeletePeopleTreePersonRequest $request, PeopleTreeWriter $writer): RedirectResponse
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return $writer->deletePerson($request);
    }

    public function saveGroup(SavePeopleTreeGroupRequest $request, PeopleTreeWriter $writer): RedirectResponse
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return $writer->saveGroup($request);
    }

    public function leavePersonGroup(LeavePeopleTreeGroupRequest $request, PeopleTreeWriter $writer): RedirectResponse
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return $writer->leavePersonGroup($request);
    }

    public function completeGroup(CompletePeopleTreeGroupRequest $request, PeopleTreeWriter $writer): RedirectResponse
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return $writer->completeGroup($request);
    }

    public function reactivateGroup(ReactivatePeopleTreeGroupRequest $request, PeopleTreeWriter $writer): RedirectResponse
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return $writer->reactivateGroup($request);
    }

    public function exportDot(ExportPeopleTreeDotRequest $request, PeopleTreeWriter $writer): RedirectResponse|Response
    {
        $redirect = $this->guardPageAccess($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return $writer->exportDot($request);
    }

    private function guardPageAccess(Request $request): ?RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        return null;
    }
}
