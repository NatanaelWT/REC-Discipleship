<?php

namespace App\Support;

final class PersonNameNormalizer
{
    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        return preg_replace_callback(
            "/(?<=['’])\p{Ll}/u",
            static fn (array $match): string => mb_strtoupper($match[0], 'UTF-8'),
            $name,
        ) ?? $name;
    }
}
