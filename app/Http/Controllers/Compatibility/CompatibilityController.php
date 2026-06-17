<?php

namespace App\Http\Controllers\Compatibility;

use App\Http\Controllers\Controller;
use App\Services\Routing\CompatibilityRouteMap;
use App\Services\PublicPortal\PublicMenuCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CompatibilityController extends Controller
{
    public function __construct(
        private readonly PublicMenuCatalog $publicMenuCatalog,
    )
    {
    }

    public function __invoke(Request $request): RedirectResponse|Response|View
    {
        $pageQuery = trim((string) $request->query('page', ''));
        if ($pageQuery !== '' && CompatibilityRouteMap::hasPage($pageQuery)) {
            $target = CompatibilityRouteMap::pageUrl($pageQuery, $request->query());

            return redirect()->away($request->getSchemeAndHttpHost() . $target);
        }

        if ($pageQuery !== '') {
            return view('public.links.index', $this->homeViewData());
        }

        return redirect()->route('home');
    }

    /**
     * @return array<string, mixed>
     */
    private function homeViewData(): array
    {
        $churchName = defined('CHURCH_NAME') ? trim((string) CHURCH_NAME) : 'Reformed Exodus Community';
        if ($churchName === '') {
            $churchName = 'Reformed Exodus Community';
        }

        return [
            'settings' => ['church_name' => $churchName],
            'churchName' => $churchName,
            'menuCards' => $this->publicMenuCatalog->cards(),
        ];
    }
}
