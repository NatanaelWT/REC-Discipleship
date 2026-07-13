<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class StableNameCursor
{
    /** @return array{name:string,id:int,is_null:bool}|null */
    public static function decode(mixed $value): ?array
    {
        $encoded = trim((string) $value);
        if ($encoded === '' || strlen($encoded) > 2048) {
            return null;
        }

        $padding = strlen($encoded) % 4;
        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (! is_string($json)) {
            return null;
        }

        $payload = json_decode($json, true);
        if (! is_array($payload) || ! is_string($payload['name'] ?? null)) {
            return null;
        }

        $name = $payload['name'];
        $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($id === false || strlen($name) > 512) {
            return null;
        }

        return [
            'name' => $name,
            'id' => (int) $id,
            'is_null' => ($payload['is_null'] ?? false) === true,
        ];
    }

    public static function encode(?string $name, int $id): ?string
    {
        if ($id < 1) {
            return null;
        }

        $json = json_encode([
            'name' => $name ?? '',
            'id' => $id,
            'is_null' => $name === null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return null;
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public static function normalizedExpression(string $expression): string
    {
        return "LOWER(TRIM(COALESCE({$expression}, '')))";
    }

    /** @param array{name:string,id:int,is_null:bool}|null $cursor */
    public static function apply(
        Builder $query,
        string $nameExpression,
        string $idColumn,
        ?array $cursor,
        bool $nullableName = false,
    ): void
    {
        if ($cursor === null) {
            return;
        }

        if ($nullableName && $cursor['is_null']) {
            $query->where(function (Builder $after) use ($nameExpression, $idColumn, $cursor): void {
                $after->where(function (Builder $remainingNulls) use ($nameExpression, $idColumn, $cursor): void {
                    $remainingNulls->whereRaw("{$nameExpression} IS NULL")
                        ->where($idColumn, '>', $cursor['id']);
                })->orWhereRaw("{$nameExpression} IS NOT NULL");
            });

            return;
        }

        $query->where(function (Builder $after) use ($nameExpression, $idColumn, $cursor): void {
            $after->whereRaw("{$nameExpression} > ?", [$cursor['name']])
                ->orWhere(function (Builder $sameName) use ($nameExpression, $idColumn, $cursor): void {
                    $sameName->whereRaw("{$nameExpression} = ?", [$cursor['name']])
                        ->where($idColumn, '>', $cursor['id']);
                });
        });
    }
}
