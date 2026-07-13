<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DgReportBranchController extends Controller
{
    public function index(Request $request): View
    {
        $branchOptions = public_dg_branch_options();

        return view('public.dg-reports.select-branch', [
            'settings' => ['church_name' => app_church_name()],
            'errorCode' => trim((string) $request->query('error', '')),
            'branchOptions' => $branchOptions,
            'branchCount' => count($branchOptions),
        ]);
    }
}
