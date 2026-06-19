<?php

use App\Services\Auth\CurrentUserContext;

function current_username(): string
{
    return app(CurrentUserContext::class)->username();
}
