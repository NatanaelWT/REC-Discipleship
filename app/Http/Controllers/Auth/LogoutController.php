<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Activity\ActivityRecorder;
use App\Services\Auth\SessionAuthenticator;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function destroy(
        Request $request,
        SessionAuthenticator $sessions,
        ActivityRecorder $activity,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);
        $activity->record(
            'auth',
            'auth.logout',
            'users',
            $request->user()?->getAuthIdentifier(),
            current_username() ?: null,
            'User keluar dari aplikasi.',
        );
        $sessions->logout($request);

        return redirect()->route('home');
    }
}
