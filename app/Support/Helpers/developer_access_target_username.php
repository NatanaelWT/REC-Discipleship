<?php

use App\Services\Auth\DeveloperAccessSession;

function developer_access_target_username(): string
{
    return app(DeveloperAccessSession::class)->targetUsername();
}
