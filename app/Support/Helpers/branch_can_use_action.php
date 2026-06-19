<?php

use App\Services\Auth\CurrentUserContext;

function branch_can_use_action(string $branch, string $action): bool
{
    return app(CurrentUserContext::class)->canUseAction($action);
}
