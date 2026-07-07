<?php

namespace App\Support;

use App\Services\AppConfig\AppConfigService;
use Illuminate\Http\Request;
use Throwable;

class RuntimeBootstrap
{
    private static bool $runtimeLoaded = false;

    /** @var array<string, true> */
    private static array $loadedHelpers = [];

    public static function boot(?Request $request = null): void
    {
        self::loadRuntime();
        self::loadHelpers(HelperManifest::forPath(trim((string) ($request?->path() ?? ''), '/')));
    }

    public static function load(): void
    {
        self::loadRuntime();
        if (app()->runningInConsole()) {
            self::loadHelpers(HelperManifest::all());

            return;
        }

        $request = app()->bound('request') ? app('request') : null;
        self::loadHelpers(HelperManifest::forPath($request instanceof Request ? trim($request->path(), '/') : ''));
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
     * @return array{church_name:string,app_timezone:string,developer_debug_banner:string}
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
