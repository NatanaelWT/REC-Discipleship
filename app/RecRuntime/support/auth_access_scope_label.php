<?php

function auth_access_scope_label(string $scope): string {
    $scope = normalize_auth_access_scope($scope);
    $labels = [
        'worship_only' => 'Ibadah Umum',
        'branch' => 'Cabang Pemuridan',
        'central_discipleship_readonly' => 'Pusat Pemuridan',
    ];
    return $labels[$scope] ?? 'Cabang Pemuridan';
}
