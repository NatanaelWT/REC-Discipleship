<?php

namespace App\Support;

use App\Services\AppConfig\AppConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Throwable;

class RuntimeBootstrap
{
    private static bool $loaded = false;

    public static function boot(?Request $request = null): void
    {
        self::load();
    }

    public static function load(): void
    {
        if (self::$loaded) {
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
        if (! defined('DISCIPLESHIP_RELATIONSHIPS_DATA_NAME')) {
            define('DISCIPLESHIP_RELATIONSHIPS_DATA_NAME', 'discipleship_relationships');
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
        self::ensureRuntimeFilesystem();

        foreach (glob(app_path('Support/Helpers/*.php')) ?: [] as $supportFile) {
            require_once $supportFile;
        }

        self::$loaded = true;
    }

    private static function ensureRuntimeFilesystem(): void
    {
        foreach (['assets', 'data', 'templates', 'uploads'] as $directory) {
            File::ensureDirectoryExists(REC_RUNTIME_PATH.DIRECTORY_SEPARATOR.$directory);
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
