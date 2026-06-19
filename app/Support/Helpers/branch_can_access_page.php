<?php

use App\Services\Auth\CurrentUserContext;

function branch_can_access_page(string $branch, string $page): bool
{
    return app(CurrentUserContext::class)->canAccessPage($page);
}
