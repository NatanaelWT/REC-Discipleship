<?php

use App\Services\Auth\CurrentUserContext;

function is_effective_central_discipleship_readonly(): bool
{
    return app(CurrentUserContext::class)->isDiscipleshipPreviewReadonly();
}
