<?php

function redirect_to(string $page, array $params = []): void {
    if (class_exists(\App\Services\Routing\CompatibilityRouteMap::class)) {
        header('Location: ' . \App\Services\Routing\CompatibilityRouteMap::pageUrl($page, $params));
        exit;
    }

    $qs = http_build_query(array_merge(['page' => $page], $params));
    header('Location: ?' . $qs);
    exit;
}
