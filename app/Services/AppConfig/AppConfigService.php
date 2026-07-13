<?php

namespace App\Services\AppConfig;

use App\Models\AppConfig;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class AppConfigService
{
    private const CACHE_KEY = 'rec.app-config.v3';

    public const DEFAULTS = [
        'church_name' => 'Reformed Exodus Community',
        'app_timezone' => 'Asia/Jakarta',
        'developer_debug_banner' => '0',
        'maintenance_mode' => '0',
    ];

    public const ALLOWED_KEYS = [
        'church_name',
        'app_timezone',
        'developer_debug_banner',
        'maintenance_mode',
    ];

    /**
     * @var array<string, string>|null
     */
    private static ?array $cachedValues = null;

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return self::loadValues();
    }

    public function value(string $key, ?string $fallback = null): string
    {
        $values = $this->values();

        return $values[$key] ?? ($fallback ?? (self::DEFAULTS[$key] ?? ''));
    }

    /**
     * @return array<string, string>
     */
    public static function runtimeValues(): array
    {
        return self::loadValues();
    }

    /**
     * @return array<int, string>
     */
    public function timezoneOptions(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input, string $updatedBy): ?string
    {
        $churchName = $this->normalizeChurchName((string) ($input['church_name'] ?? ''));
        if ($churchName === '') {
            return 'church_name_required';
        }

        $timezone = $this->normalizeTimezone((string) ($input['app_timezone'] ?? ''));
        if ($timezone === '') {
            return 'timezone_invalid';
        }

        $values = [
            'church_name' => $churchName,
            'app_timezone' => $timezone,
            'developer_debug_banner' => $this->normalizeBooleanString($input['developer_debug_banner'] ?? '0'),
            'maintenance_mode' => $this->normalizeBooleanString($input['maintenance_mode'] ?? '0'),
        ];

        foreach ($values as $key => $value) {
            AppConfig::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'updated_by' => $updatedBy !== '' ? $updatedBy : null,
                ],
            );
        }

        self::clearCache();
        if (function_exists('config')) {
            config(['app.timezone' => $values['app_timezone']]);
        }
        date_default_timezone_set($values['app_timezone']);

        return null;
    }

    public static function clearCache(): void
    {
        self::$cachedValues = null;
        Cache::store(self::cacheStore())->forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private static function loadValues(): array
    {
        if (self::$cachedValues !== null) {
            return self::$cachedValues;
        }

        try {
            $stored = Cache::store(self::cacheStore())->remember(
                self::CACHE_KEY,
                now()->addMinutes(5),
                static fn (): array => DB::table('konfigurasi')
                    ->whereIn('key', self::ALLOWED_KEYS)
                    ->pluck('value', 'key')
                    ->all(),
            );
        } catch (Throwable) {
            return self::$cachedValues = self::DEFAULTS;
        }

        $values = self::DEFAULTS;
        foreach ($stored as $key => $value) {
            if (! is_string($key) || ! in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }

            $normalized = self::normalizeStoredValue($key, (string) $value);
            if ($normalized !== '') {
                $values[$key] = $normalized;
            }
        }

        return self::$cachedValues = $values;
    }

    private static function cacheStore(): string
    {
        return (string) config('cache.discipleship_store', config('cache.default', 'file'));
    }

    private static function normalizeStoredValue(string $key, string $value): string
    {
        $service = new self;

        return match ($key) {
            'church_name' => $service->normalizeChurchName($value) ?: self::DEFAULTS['church_name'],
            'app_timezone' => $service->normalizeTimezone($value) ?: self::DEFAULTS['app_timezone'],
            'developer_debug_banner' => $service->normalizeBooleanString($value),
            'maintenance_mode' => $service->normalizeBooleanString($value),
            default => '',
        };
    }

    private function normalizeChurchName(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, 120) : substr($value, 0, 120);
    }

    private function normalizeTimezone(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return in_array($value, DateTimeZone::listIdentifiers(), true) ? $value : '';
    }

    private function normalizeBooleanString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }
}
