<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Services\DgMeetingReports\DgMeetingReportRecapPageData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeetingReportRecapController extends Controller
{
    public function index(Request $request, DgMeetingReportRecapPageData $pageData): Response
    {
        RuntimeBootstrap::boot($request);

        return response(view('discipleship.meeting-reports.recap', $pageData->forCurrentContext($request))->render());
    }
}
