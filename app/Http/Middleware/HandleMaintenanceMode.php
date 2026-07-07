<?php

namespace App\Http\Middleware;

use App\Enums\UserAccessRole;
use App\Models\User;
use App\Services\Auth\SessionAuthenticator;
use App\Support\RuntimeBootstrap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleMaintenanceMode
{
    private const READ_ONLY_ROUTE_NAMES = [
        'home',
        'index.redirect',
        'auth.login',
        'materials.index',
        'materials.show',
        'materials.preview.redirect',
        'materials.preview',
        'materials.download.redirect',
        'materials.download',
    ];

    public function __construct(private readonly SessionAuthenticator $sessions) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        RuntimeBootstrap::boot($request);

        if (! app_maintenance_mode_enabled()) {
            return $next($request);
        }

        if ($this->authenticatedOriginalUserIsDeveloper()) {
            return $next($request);
        }

        if (Auth::check()) {
            $this->sessions->logout($request);

            return $this->blockedResponse($request, true);
        }

        if ($this->isAllowedAnonymousRequest($request)) {
            return $next($request);
        }

        return $this->blockedResponse($request, false);
    }

    private function authenticatedOriginalUserIsDeveloper(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && (bool) ($user->is_active ?? true)
            && UserAccessRole::fromStoredValue((string) $user->access_scope) === UserAccessRole::Developer;
    }

    private function isAllowedAnonymousRequest(Request $request): bool
    {
        $routeName = trim((string) ($request->route()?->getName() ?? ''));
        if ($request->isMethod('POST')) {
            return $routeName === 'auth.login.store';
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        return in_array($routeName, self::READ_ONLY_ROUTE_NAMES, true);
    }

    private function blockedResponse(Request $request, bool $loggedOut): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Aplikasi sedang maintenance. Akses sementara hanya tersedia untuk developer.',
            ], 503);
        }

        if ($loggedOut) {
            return redirect()->route('auth.login', ['maintenance' => 1]);
        }

        return redirect()->route('materials.show', [
            'menu' => 'materi_dg_1',
            'maintenance' => 1,
        ]);
    }
}
