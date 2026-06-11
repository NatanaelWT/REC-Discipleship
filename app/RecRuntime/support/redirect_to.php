<?php

function redirect_to(string $page, array $params = []): void {
    if (class_exists(\App\Services\Legacy\LegacyRouteMap::class)) {
        header('Location: ' . \App\Services\Legacy\LegacyRouteMap::pageUrl($page, $params));
        legacy_exit();
    }

    $qs = http_build_query(array_merge(['page' => $page], $params));
    header('Location: ?' . $qs);
    legacy_exit();
}
