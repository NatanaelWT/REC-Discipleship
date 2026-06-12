<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Services\Legacy\LegacyRenderer;
use App\Services\Legacy\LegacyRouteMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompatibilityController extends Controller
{
    public function __construct(private readonly LegacyRenderer $renderer)
    {
    }

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $legacyPage = trim((string) $request->query('page', ''));
        if ($legacyPage !== '' && LegacyRouteMap::hasPage($legacyPage)) {
            if (! $request->isMethod('GET')) {
                return $this->renderer->render($request, $legacyPage);
            }

            $target = $this->renderer->cleanUrlForLegacyPage($legacyPage, $request->query());

            return redirect()->away($request->getSchemeAndHttpHost() . $target);
        }

        return $this->renderer->render($request, 'kutisari');
    }
}
