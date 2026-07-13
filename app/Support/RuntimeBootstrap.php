<?php

namespace App\Support;

use App\Services\AppConfig\AppConfigService;
use Illuminate\Http\Request;
use Throwable;

class RuntimeBootstrap
{
    private const REQUEST_BOOTSTRAPPED = 'rec.runtime_bootstrap.bootstrapped';

    private const REQUEST_BOOT_COUNT = 'rec.runtime_bootstrap.actual_count';

    private const REQUEST_HELPER_COUNT = 'rec.runtime_bootstrap.helper_count';

    private static bool $runtimeLoaded = false;

    /** @var array<string, true> */
    private static array $loadedHelpers = [];

    public static function boot(?Request $request = null): void
    {
        if ($request instanceof Request) {
            self::bootHttpRequest($request);

            return;
        }

        self::load();
    }

    /**
     * Bootstrap one HTTP request. The request attribute is deliberately used
     * instead of a process-wide flag so this remains correct under long-lived
     * workers and during feature tests that send several requests in one PHP
     * process.
     */
    public static function bootHttpRequest(Request $request): void
    {
        if ($request->attributes->getBoolean(self::REQUEST_BOOTSTRAPPED)) {
            return;
        }

        $request->attributes->set(self::REQUEST_BOOTSTRAPPED, true);
        $request->attributes->set(
            self::REQUEST_BOOT_COUNT,
            (int) $request->attributes->get(self::REQUEST_BOOT_COUNT, 0) + 1,
        );

        $helpers = HelperManifest::forPath(trim($request->path(), '/'));
        $request->attributes->set(self::REQUEST_HELPER_COUNT, count($helpers));

        self::loadRuntime();
        self::loadHelpers($helpers);
    }

    public static function load(): void
    {
        self::loadRuntime();

        // PHPUnit and other console hosts may still be serving a real HTTP
        // request. Prefer the request-scoped manifest once the web bootstrap
        // has marked it, rather than accidentally loading every helper.
        $request = app()->bound('request') ? app('request') : null;
        if ($request instanceof Request && $request->attributes->getBoolean(self::REQUEST_BOOTSTRAPPED)) {
            self::loadHelpers(HelperManifest::forPath(trim($request->path(), '/')));

            return;
        }

        if (app()->runningInConsole()) {
            self::loadHelpers(HelperManifest::all());

            return;
        }

        self::loadHelpers(HelperManifest::forPath($request instanceof Request ? trim($request->path(), '/') : ''));
    }

    public static function httpBootCount(Request $request): int
    {
        return (int) $request->attributes->get(self::REQUEST_BOOT_COUNT, 0);
    }

    public static function httpHelperCount(Request $request): int
    {
        return (int) $request->attributes->get(self::REQUEST_HELPER_COUNT, 0);
    }

    private static function loadRuntime(): void
    {
        if (self::$runtimeLoaded) {
            return;
        }

        $runtimeSettings = self::runtimeSettings();
        if (! defined('APP_TIMEZONE')) {
            define('APP_TIMEZONE', $runtimeSettings['app_timezone']);
        }
        if (! defined('CHURCH_NAME')) {
            define('CHURCH_NAME', $runtimeSettings['church_name']);
        }
        if (! defined('DISCIPLESHIP_GROUPS_DATA_NAME')) {
            define('DISCIPLESHIP_GROUPS_DATA_NAME', 'discipleship_groups');
        }
        if (! defined('REC_RUNTIME_PATH')) {
            define('REC_RUNTIME_PATH', storage_path('app/private'));
        }
        if (! defined('REC_PUBLIC_PATH')) {
            define('REC_PUBLIC_PATH', public_path());
        }

        if (function_exists('config')) {
            config(['app.timezone' => $runtimeSettings['app_timezone']]);
        }
        date_default_timezone_set($runtimeSettings['app_timezone']);
        self::$runtimeLoaded = true;
    }

    /** @param array<int, string> $helpers */
    private static function loadHelpers(array $helpers): void
    {
        foreach ($helpers as $helper) {
            if (isset(self::$loadedHelpers[$helper])) {
                continue;
            }
            $path = app_path('Support/Helpers/'.$helper.'.php');
            if (is_file($path)) {
                require_once $path;
                self::$loadedHelpers[$helper] = true;
            }
        }
    }

    /**
     * @return array{church_name:string,app_timezone:string,developer_debug_banner:string,maintenance_mode:string}
     */
    private static function runtimeSettings(): array
    {
        try {
            return AppConfigService::runtimeValues();
        } catch (Throwable) {
            return AppConfigService::DEFAULTS;
        }
    }
}
