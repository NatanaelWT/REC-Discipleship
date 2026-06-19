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
        self::startSession($request);
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

        foreach (glob(resource_path('views/partials/*.blade.php')) ?: [] as $partialFile) {
            if (basename($partialFile) === 'people_tree_group_history_content.blade.php') {
                continue;
            }

            require_once $partialFile;
        }

        self::$loaded = true;
    }

    public static function startSession(?Request $request = null): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $httpsEnabled = $request !== null
            ? $request->isSecure()
            : ((! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443));

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        if ($httpsEnabled) {
            ini_set('session.cookie_secure', '1');
        }

        session_name('rec_admin_session');
        session_start();
    }

    private static function ensureRuntimeFilesystem(): void
    {
        foreach (['assets', 'data', 'templates', 'uploads'] as $directory) {
            File::ensureDirectoryExists(REC_RUNTIME_PATH . DIRECTORY_SEPARATOR . $directory);
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
