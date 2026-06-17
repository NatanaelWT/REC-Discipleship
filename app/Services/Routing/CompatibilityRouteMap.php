<?php

namespace App\Services\Routing;

class CompatibilityRouteMap
{
    /**
     * @return array<string, string>
     */
    public static function pages(): array
    {
        /** @var array<string, string> $pages */
        $pages = config('compatibility.pages', []);

        return $pages;
    }

    public static function pagePath(string $page): string
    {
        $page = trim($page);
        $pages = self::pages();

        return $pages[$page] ?? '/';
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function pageUrl(string $page, array $params = []): string
    {
        unset($params['page']);

        $path = self::pagePath($page);
        $query = http_build_query($params);

        return $query === '' ? $path : $path . '?' . $query;
    }

    public static function pageForPath(string $path): ?string
    {
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        foreach (self::pages() as $page => $pagePath) {
            if ($pagePath === $path) {
                return $page;
            }
        }

        return null;
    }

    public static function hasPage(string $page): bool
    {
        return array_key_exists($page, self::pages());
    }
}
