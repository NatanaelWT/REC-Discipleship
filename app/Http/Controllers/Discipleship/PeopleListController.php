<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleListData;
use App\Services\Routing\CompatibilityRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeopleListController extends Controller
{
    public function index(Request $request, DiscipleshipPeopleListData $peopleListData): RedirectResponse|View
    {
        $pageQuery = trim((string) $request->query('page', ''));
        if ($pageQuery !== '' && CompatibilityRouteMap::hasPage($pageQuery)) {
            return redirect()->away($request->getSchemeAndHttpHost() . CompatibilityRouteMap::pageUrl($pageQuery, $request->query()));
        }

        RuntimeBootstrap::boot($request);

        if (trim((string) $request->input('action', '')) === 'logout') {
            destroy_current_session();

            return redirect('/index.php');
        }

        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }

        if (! branch_can_access_page(current_user_branch(), 'people_list')) {
            return redirect(CompatibilityRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied']));
        }

        return view('discipleship.people-list.index', $peopleListData->forCurrentContext());
    }
}
