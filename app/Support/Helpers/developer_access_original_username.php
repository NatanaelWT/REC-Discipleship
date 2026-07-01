<?php

use App\Services\Auth\DeveloperAccessSession;

function developer_access_original_username(): string
{
    return app(DeveloperAccessSession::class)->originalUsername();
}
