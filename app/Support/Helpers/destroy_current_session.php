<?php

use Illuminate\Support\Facades\Auth;

function destroy_current_session(): void
{
    Auth::logout();
    if (request()->hasSession()) {
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
