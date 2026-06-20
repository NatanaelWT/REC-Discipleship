<?php

use App\Services\Auth\CurrentUserContext;

function current_user_branch_id(): ?int
{
    return app(CurrentUserContext::class)->branchId();
}
