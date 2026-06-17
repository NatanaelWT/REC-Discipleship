<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Services\Routing\CompatibilityRouteMap;
use App\Services\PublicPortal\PublicMenuCatalog;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(
        Request $request,
        PublicMenuCatalog $catalog,
    ): RedirectResponse|View {
        RuntimeBootstrap::boot($request);

        $pageQuery = trim((string) $request->query('page', ''));
        if ($pageQuery !== '' && CompatibilityRouteMap::hasPage($pageQuery)) {
            return redirect()->away($request->getSchemeAndHttpHost() . CompatibilityRouteMap::pageUrl($pageQuery, $request->query()));
        }

        return view('public.links.index', $this->homeViewData($catalog));
    }

    public function emptyMenu(Request $request, PublicMenuCatalog $catalog): View
    {
        RuntimeBootstrap::boot($request);

        return view('public.links.empty', [
            'settings' => ['church_name' => CHURCH_NAME],
            'menuLabel' => $catalog->emptyMenuLabel((string) $request->query('menu', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function homeViewData(PublicMenuCatalog $catalog): array
    {
        $churchName = trim((string) (CHURCH_NAME));
        if ($churchName === '') {
            $churchName = 'Reformed Exodus Community';
        }

        return [
            'settings' => ['church_name' => $churchName],
            'churchName' => $churchName,
            'menuCards' => $catalog->cards(),
        ];
    }
}
