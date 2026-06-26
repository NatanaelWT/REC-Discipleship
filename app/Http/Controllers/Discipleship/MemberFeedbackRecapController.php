<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Services\MemberFeedbackJournals\MemberFeedbackRecapPageData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberFeedbackRecapController extends Controller
{
    public function index(Request $request, MemberFeedbackRecapPageData $pageData): View
    {
        RuntimeBootstrap::boot($request);

        return view('discipleship.member-feedback.recap', $pageData->forCurrentContext($request));
    }
}
