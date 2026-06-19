<?php

use App\Services\Auth\CurrentUserContext;

function is_superuser_session(): bool
{
    return app(CurrentUserContext::class)->isDeveloper();
}
