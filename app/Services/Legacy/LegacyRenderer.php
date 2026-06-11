<?php

namespace App\Services\Legacy;

use App\Support\LegacyDataStore;
use App\Support\LegacyExit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LegacyRenderer
{
    public function render(Request $request, string $page): Response
    {
        LegacyDataStore::prepareRuntime();
        LegacyDataStore::registerShutdownSync();

        if (! defined('REC_LEGACY_RUNTIME_PATH')) {
            define('REC_LEGACY_RUNTIME_PATH', LegacyDataStore::runtimeRoot());
        }

        if (! defined('REC_LEGACY_PUBLIC_PATH')) {
            define('REC_LEGACY_PUBLIC_PATH', public_path());
        }

        $previousGet = $_GET;
        $previousRequest = $_REQUEST;

        $_GET = $request->query->all();
        $_GET['page'] = $page;
        $_REQUEST = array_merge($_REQUEST, $_GET, $_POST);

        ob_start();
        try {
            require app_path('RecRuntime/index.php');
        } catch (LegacyExit) {
            // The legacy renderer uses legacy_exit() as its normal terminator.
        } finally {
            $_GET = $previousGet;
            $_REQUEST = $previousRequest;
        }

        $content = (string) ob_get_clean();
        $status = http_response_code();
        $headers = headers_list();

        header_remove();

        $response = response($this->rewriteContentUrls($content), is_int($status) ? $status : 200);
        foreach ($headers as $headerLine) {
            if (strpos($headerLine, ':') === false) {
                continue;
            }

            [$name, $value] = explode(':', $headerLine, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if (strcasecmp($name, 'Location') === 0) {
                $value = $this->rewriteLocation($value);
                if (($status === false || $status === 200) && $value !== '') {
                    $response->setStatusCode(302);
                }
            }

            $response->headers->set($name, $value, false);
        }

        return $response;
    }

    public function cleanUrlForLegacyPage(string $page, array $params = []): string
    {
        return LegacyRouteMap::pageUrl($page, $params);
    }

    private function rewriteLocation(string $location): string
    {
        $location = trim($location);
        if ($location === '' || $location === 'index.php') {
            return '/';
        }

        if (str_starts_with($location, '?')) {
            parse_str(ltrim($location, '?'), $params);
            $page = trim((string) ($params['page'] ?? ''));
            if ($page !== '' && LegacyRouteMap::hasPage($page)) {
                return LegacyRouteMap::pageUrl($page, $params);
            }
        }

        return $location;
    }

    private function rewriteContentUrls(string $content): string
    {
        $content = preg_replace_callback(
            '/\b(href|action)=(["\'])(?:index\.php)?\?([^"\']*?\bpage=[^"\']*)(\2)/i',
            function (array $matches): string {
                $attribute = $matches[1];
                $quote = $matches[2];
                $query = html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                parse_str($query, $params);
                $page = trim((string) ($params['page'] ?? ''));
                if ($page === '' || ! LegacyRouteMap::hasPage($page)) {
                    return $matches[0];
                }

                return $attribute . '=' . $quote . e(LegacyRouteMap::pageUrl($page, $params)) . $quote;
            },
            $content
        ) ?? $content;

        return str_replace(
            ['href="index.php"', "href='index.php'"],
            ['href="/"', "href='/'"],
            $content
        );
    }
}
