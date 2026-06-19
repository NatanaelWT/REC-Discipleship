<?php

function normalize_auth_access_scope(string $scope): string {
    $scope = strtolower(trim($scope));
    if ($scope === 'developer') {
        return $scope;
    }
    if ($scope === 'worship_only') {
        return $scope;
    }
    if ($scope === 'central_discipleship_readonly') {
        return $scope;
    }
    if ($scope === 'branch' || $scope === 'discipleship_branch') {
        return 'branch';
    }
    return 'branch';
}
