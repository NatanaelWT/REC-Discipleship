<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\Analytics\WebsiteStatisticsService;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeveloperStatisticsController extends Controller
{
    public function index(Request $request, WebsiteStatisticsService $statistics): RedirectResponse|View
    {
        RuntimeBootstrap::boot($request);
        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }
        if (! can_manage_users()) {
            abort(403, 'Akses developer diperlukan.');
        }

        return view('developer.statistics.index', array_merge(
            [
                'settings' => ['church_name' => app_church_name()],
                'currentPage' => 'developer_statistics',
            ],
            $statistics->dashboard($request),
        ));
    }
}
