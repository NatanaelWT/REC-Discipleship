<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthCredentialService;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\LoginAttemptLimiter;
use App\Services\Auth\SessionAuthenticator;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(Request $request, CurrentUserContext $context, SessionAuthenticator $sessions): RedirectResponse|View
    {
        RuntimeBootstrap::boot($request);

        if (Auth::check() && ! $context->isLoggedIn()) {
            $sessions->logout($request);

            return redirect()->route('auth.login', ['account_removed' => 1]);
        }

        if ($context->isLoggedIn()) {
            return redirect(AppPageRouteMap::pageUrl($context->homePage()));
        }

        return view('auth.login', $this->viewData($request));
    }

    public function store(
        LoginRequest $request,
        AuthCredentialService $credentials,
        LoginAttemptLimiter $limiter,
        SessionAuthenticator $sessions,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        $ip = function_exists('client_ip_address') ? client_ip_address() : (string) ($request->ip() ?? 'unknown');
        $now = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));
        $waitSeconds = $limiter->waitSeconds($ip, $now);
        if ($waitSeconds > 0) {
            return redirect()->route('auth.login', ['error' => 'locked', 'wait' => $waitSeconds]);
        }

        $user = $credentials->attempt(
            (string) $request->input('username', ''),
            (string) $request->input('password', ''),
        );

        if ($user !== null) {
            $limiter->clear($ip);
            $credentials->updateLastLogin($user, $now);
            $sessions->login($request, $user);

            return redirect(AppPageRouteMap::pageUrl(app(CurrentUserContext::class)->homePage()));
        }

        $waitSeconds = $limiter->registerFailure($ip, $now);
        if ($waitSeconds > 0) {
            return redirect()->route('auth.login', ['error' => 'locked', 'wait' => $waitSeconds]);
        }

        return redirect()->route('auth.login', ['error' => 1]);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Request $request): array
    {
        return [
            'settings' => ['church_name' => app_church_name()],
            'errorCode' => trim((string) $request->query('error', '')),
            'waitSeconds' => max(0, (int) $request->query('wait', 0)),
            'expired' => $request->query->has('expired'),
            'accountRemoved' => $request->query->has('account_removed'),
        ];
    }
}
