<?php

use App\Services\Auth\CurrentUserContext;

function current_user_can_access_worship(): bool
{
    return app(CurrentUserContext::class)->canAccessWorship();
}
