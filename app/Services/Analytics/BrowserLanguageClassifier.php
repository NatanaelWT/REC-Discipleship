<?php

namespace App\Services\Analytics;

class BrowserLanguageClassifier
{
    /** @var array<string, string> */
    private const LANGUAGE_NAMES = [
        'ar' => 'Arab',
        'de' => 'Jerman',
        'en' => 'Inggris',
        'es' => 'Spanyol',
        'fr' => 'Prancis',
        'id' => 'Indonesia',
        'it' => 'Italia',
        'ja' => 'Jepang',
        'ko' => 'Korea',
        'ms' => 'Melayu',
        'nl' => 'Belanda',
        'pt' => 'Portugis',
        'ru' => 'Rusia',
        'th' => 'Thailand',
        'vi' => 'Vietnam',
        'zh' => 'Tionghoa',
    ];

    /** @return array{language_code:?string,language_name:?string} */
    public function classify(?string $acceptLanguage): array
    {
        $candidates = [];
        foreach (explode(',', trim((string) $acceptLanguage)) as $index => $entry) {
            $parts = array_map('trim', explode(';', $entry));
            $code = $this->normalize((string) ($parts[0] ?? ''));
            if ($code === null) {
                continue;
            }

            $quality = 1.0;
            $valid = true;
            foreach (array_slice($parts, 1) as $parameter) {
                if (preg_match('/^q=(0(?:\.\d{1,3})?|1(?:\.0{1,3})?)$/i', $parameter, $matches) === 1) {
                    $quality = (float) $matches[1];
                } elseif (str_starts_with(strtolower($parameter), 'q=')) {
                    $valid = false;
                    break;
                }
            }
            if (! $valid || $quality <= 0) {
                continue;
            }
            $candidates[] = ['code' => $code, 'quality' => $quality, 'index' => $index];
        }

        usort($candidates, static fn (array $left, array $right): int => ($right['quality'] <=> $left['quality']) ?: ($left['index'] <=> $right['index']));
        $code = $candidates[0]['code'] ?? null;
        if (! is_string($code)) {
            return ['language_code' => null, 'language_name' => null];
        }

        $base = strtolower(explode('-', $code)[0]);
        $name = self::LANGUAGE_NAMES[$base] ?? strtoupper($base);

        return [
            'language_code' => $code,
            'language_name' => $name.($code !== $base ? ' ('.$code.')' : ''),
        ];
    }

    private function normalize(string $code): ?string
    {
        $code = str_replace('_', '-', trim($code));
        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $code) !== 1) {
            return null;
        }

        $parts = explode('-', $code);
        $parts[0] = strtolower($parts[0]);
        foreach ($parts as $index => $part) {
            if ($index === 0) {
                continue;
            }
            $parts[$index] = strlen($part) === 2 ? strtoupper($part) : strtolower($part);
        }

        return implode('-', $parts);
    }
}
