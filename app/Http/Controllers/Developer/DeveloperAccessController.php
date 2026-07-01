<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\DeveloperAccessSession;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeveloperAccessController extends Controller
{
    public function start(
        Request $request,
        User $user,
        DeveloperAccessSession $access,
        ActivityRecorder $activity,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        $error = $access->start($request, $user);
        if ($error !== null) {
            return redirect()->route('developer.users', [
                'error' => $error,
                'user' => (int) $user->getKey(),
            ]);
        }

        $activity->record(
            'developer',
            'developer.access.started',
            'users',
            $user->getKey(),
            (string) $user->username,
            'Developer memakai akses user.',
            metadata: $access->metadata($user),
        );

        return redirect(AppPageRouteMap::pageUrl(app(CurrentUserContext::class)->homePage()));
    }

    public function stop(
        Request $request,
        DeveloperAccessSession $access,
        ActivityRecorder $activity,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        if (! $access->active()) {
            return redirect(AppPageRouteMap::pageUrl(app(CurrentUserContext::class)->homePage()));
        }

        $metadata = $access->metadata();
        $activity->record(
            'developer',
            'developer.access.stopped',
            'users',
            $metadata['target_user_id'] ?? null,
            $metadata['target_username'] ?? null,
            'Developer kembali ke akses asli.',
            metadata: $metadata,
        );

        $access->stop($request);

        return redirect()->route('developer.users', ['status' => 'access_returned']);
    }
}
