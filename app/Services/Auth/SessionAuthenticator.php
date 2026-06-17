<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

class SessionAuthenticator
{
    public function login(User $user, CarbonInterface $loginAt, AuthCredentialService $credentials): void
    {
        $payload = $credentials->sessionPayload($user);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        if (function_exists('clear_removed_session_state')) {
            clear_removed_session_state();
        }

        $_SESSION['user'] = $payload['username'];
        $_SESSION['cabang'] = $payload['cabang'];
        $_SESSION['access_scope'] = $payload['access_scope'];
        $_SESSION['login_at'] = $loginAt->format('Y-m-d\TH:i:sP');
        $_SESSION['last_active_at'] = time();
        unset($_SESSION['role']);
    }

    public function logout(): void
    {
        if (function_exists('destroy_current_session')) {
            destroy_current_session();

            return;
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
