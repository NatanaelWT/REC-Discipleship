<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DgReportBranchController extends Controller
{
    public function index(Request $request): View
    {
        RuntimeBootstrap::boot($request);

        $branchOptions = public_dg_branch_options();

        return view('public.dg-reports.select-branch', [
            'settings' => ['church_name' => CHURCH_NAME],
            'errorCode' => trim((string) $request->query('error', '')),
            'branchOptions' => $branchOptions,
            'branchCount' => count($branchOptions),
        ]);
    }
}
