<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SessionAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function destroy(
        Request $request,
        SessionAuthenticator $sessions,
    ): RedirectResponse {
        $sessions->logout($request);

        return redirect()->route('home');
    }
}
