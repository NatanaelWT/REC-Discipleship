<?php

use App\Services\Auth\CurrentUserContext;

function secure_upload_prefixes_for_current_scope(): array
{
    $context = app(CurrentUserContext::class);
    $scope = $context->accessScope();
    if (is_worship_only_scope($scope)) {
        return [];
    }
    if (is_discipleship_branch_scope($scope) || $context->isCentralDiscipleshipReadonly()) {
        return restricted_secure_upload_prefixes();
    }

    return [];
}
