<?php

use App\Services\Auth\CurrentUserContext;

function is_central_discipleship_readonly_session(): bool
{
    return app(CurrentUserContext::class)->isCentralDiscipleshipReadonly();
}
