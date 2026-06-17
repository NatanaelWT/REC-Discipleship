<?php

function render_sidebar_nav_link(string $label, string $href, bool $active, string $indent = '        '): void {
    $class = $active ? 'nav-item active' : 'nav-item';
    $href = render_sidebar_nav_href($href);
    echo $indent . "<a class=\"" . h($class) . "\" href=\"" . h($href) . "\">" . h($label) . "</a>\n";
}

function render_sidebar_nav_href(string $href): string {
    $href = trim($href);
    if ($href === '' || !str_starts_with($href, '?')) {
        return $href;
    }

    parse_str(ltrim($href, '?'), $params);
    $page = trim((string) ($params['page'] ?? ''));
    if ($page === '' || !class_exists(\App\Services\Routing\CompatibilityRouteMap::class)) {
        return $href;
    }

    if (!\App\Services\Routing\CompatibilityRouteMap::hasPage($page)) {
        return $href;
    }

    return \App\Services\Routing\CompatibilityRouteMap::pageUrl($page, $params);
}
