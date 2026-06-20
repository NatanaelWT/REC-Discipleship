<?php

function is_discipleship_branch_scope(string $scope): bool {
    return normalize_auth_access_scope($scope) === 'pemuridan_cabang';
}
