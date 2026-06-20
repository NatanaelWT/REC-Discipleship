<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionAuthenticator
{
    public function login(Request $request, User $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['developer_branch', 'developer_branch_id']);
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
