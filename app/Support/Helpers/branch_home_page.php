<?php

use App\Services\Auth\CurrentUserContext;

function branch_home_page(string $branch): string
{
    return app(CurrentUserContext::class)->homePage();
}
