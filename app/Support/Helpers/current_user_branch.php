<?php

use App\Services\Auth\CurrentUserContext;

function current_user_branch(): string
{
    return app(CurrentUserContext::class)->branch();
}
