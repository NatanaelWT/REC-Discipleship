<?php

use App\Services\AppConfig\AppConfigService;

function app_config_value(string $key, ?string $fallback = null): string {
    try {
        return app(AppConfigService::class)->value($key, $fallback);
    } catch (Throwable) {
        return $fallback ?? (AppConfigService::DEFAULTS[$key] ?? '');
    }
}
