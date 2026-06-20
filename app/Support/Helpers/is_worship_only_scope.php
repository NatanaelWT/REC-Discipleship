<?php

function is_worship_only_scope(string $scope): bool {
    return normalize_auth_access_scope($scope) === 'pelayan';
}
