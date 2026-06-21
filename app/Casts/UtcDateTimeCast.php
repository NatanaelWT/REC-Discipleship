<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps explicitly UTC audit columns independent from the application's display timezone.
 *
 * @implements CastsAttributes<CarbonImmutable|null, DateTimeInterface|string|null>
 */
class UtcDateTimeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse((string) $value, (string) config('app.timezone', 'Asia/Jakarta'));

        return $date->utc()->format('Y-m-d H:i:s.u');
    }
}
