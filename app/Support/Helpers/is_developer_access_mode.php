<?php

use App\Services\Auth\DeveloperAccessSession;

function is_developer_access_mode(): bool
{
    return app(DeveloperAccessSession::class)->active();
}
