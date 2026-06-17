<?php

function is_central_discipleship_readonly_session(): bool {
    $sessionUser = trim((string) ($_SESSION['user'] ?? ''));
    if ($sessionUser === '') {
        return false;
    }
    $scope = current_auth_access_scope();
    return $scope === 'central_discipleship_readonly';
}
