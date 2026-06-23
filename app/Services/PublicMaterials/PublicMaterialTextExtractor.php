<?php

namespace App\Services\PublicMaterials;

use App\Enums\PublicMaterialMenuKey;
use Illuminate\Support\Carbon;
use Smalot\PdfParser\Parser;
use Throwable;

class PublicMaterialTextExtractor
{
    /**
     * @return array{text_content: string|null, text_extracted_at: Carbon, text_extraction_error: string|null}|array{}
     */
    public function extractForStorage(PublicMaterialMenuKey $menu, string $fullPath): array
    {
        if (! $this->shouldExtract($menu, $fullPath)) {
            return [];
        }

        $extractedAt = now();

        try {
            if (! is_file($fullPath)) {
                return $this->failedPayload($extractedAt, 'File PDF tidak ditemukan.');
            }

            $text = $this->normalizeText((new Parser)->parseFile($fullPath)->getText());
            if ($text === '') {
                return $this->failedPayload($extractedAt, 'Teks PDF tidak ditemukan.');
            }

            return [
                'text_content' => $text,
                'text_extracted_at' => $extractedAt,
                'text_extraction_error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->failedPayload($extractedAt, $exception->getMessage());
        }
    }

    public function shouldExtract(PublicMaterialMenuKey $menu, string $path): bool
    {
        return $menu === PublicMaterialMenuKey::MateriDg1
            && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\f", "\n\n", $text);

        $cleaned = preg_replace('/[\x00-\x08\x0B\x0E-\x1F\x7F]+/u', ' ', $text);
        if (is_string($cleaned)) {
            $text = $cleaned;
        } else {
            $text = preg_replace('/[\x00-\x08\x0B\x0E-\x1F\x7F]+/', ' ', $text) ?? $text;
        }

        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/^[ \t]+$/m', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array{text_content: null, text_extracted_at: Carbon, text_extraction_error: string}
     */
    private function failedPayload(Carbon $extractedAt, string $error): array
    {
        $error = trim($error);
        if ($error === '') {
            $error = 'Ekstraksi teks PDF gagal.';
        }

        return [
            'text_content' => null,
            'text_extracted_at' => $extractedAt,
            'text_extraction_error' => function_exists('mb_substr') ? mb_substr($error, 0, 1000) : substr($error, 0, 1000),
        ];
    }
}
