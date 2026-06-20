<?php

use App\Enums\UserAccessRole;

function auth_access_scope_label(string $scope): string {
    return UserAccessRole::fromStoredValue($scope)->label();
}
