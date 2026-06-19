<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SessionAuthenticator;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function destroy(Request $request, SessionAuthenticator $sessions): RedirectResponse
    {
        RuntimeBootstrap::boot($request);
        $sessions->logout($request);

        return redirect()->route('home');
    }
}
