<?php

use App\Services\Auth\CurrentUserContext;

function is_logged_in(): bool
{
    return app(CurrentUserContext::class)->isLoggedIn();
}
