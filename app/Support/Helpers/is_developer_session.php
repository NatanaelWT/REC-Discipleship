<?php

use App\Services\Auth\CurrentUserContext;

function is_developer_session(): bool
{
    return app(CurrentUserContext::class)->isDeveloper();
}
