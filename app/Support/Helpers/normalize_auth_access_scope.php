<?php

use App\Enums\UserAccessRole;

function normalize_auth_access_scope(string $scope): string {
    return UserAccessRole::fromStoredValue($scope)->value;
}
