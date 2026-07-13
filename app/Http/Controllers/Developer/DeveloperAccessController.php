<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\DeveloperAccessSession;
use App\Services\Routing\AppPageRouteMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeveloperAccessController extends Controller
{
    public function start(
        Request $request,
        User $user,
        DeveloperAccessSession $access,
    ): RedirectResponse {
        $error = $access->start($request, $user);
        if ($error !== null) {
            return redirect()->route('developer.users', [
                'error' => $error,
                'user' => (int) $user->getKey(),
            ]);
        }

        return redirect(AppPageRouteMap::pageUrl(app(CurrentUserContext::class)->homePage()));
    }

    public function stop(
        Request $request,
        DeveloperAccessSession $access,
    ): RedirectResponse {
        if (! $access->active()) {
            return redirect(AppPageRouteMap::pageUrl(app(CurrentUserContext::class)->homePage()));
        }

        $access->stop($request);

        return redirect()->route('developer.users', ['status' => 'access_returned']);
    }
}
