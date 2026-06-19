<?php

use App\Services\Auth\CurrentUserContext;

function current_auth_access_scope(): string
{
    return app(CurrentUserContext::class)->accessScope();
}
