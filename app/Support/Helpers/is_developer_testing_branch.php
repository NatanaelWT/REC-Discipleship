<?php

use App\Services\Auth\CurrentUserContext;

function is_developer_testing_branch(): bool
{
    return app(CurrentUserContext::class)->isDeveloperTestingBranch();
}
