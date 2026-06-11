<?php

function worship_penatalayan_png_wrap_lines(string $text, int $maxWidth, int $fontSize, string $fontPath): array {
    $maxWidth = max(24, $maxWidth);
    $paragraphs = preg_split("/\r\n?|\n/", trim($text)) ?: [];
    $wrapped = [];

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim((string) $paragraph);
        if ($paragraph === '') {
            $wrapped[] = '';
            continue;
        }

        $words = preg_split('/\s+/u', $paragraph) ?: [];
        $currentLine = '';
        foreach ($words as $word) {
            $word = trim((string) $word);
            if ($word === '') {
                continue;
            }

            $candidate = $currentLine !== '' ? ($currentLine . ' ' . $word) : $word;
            $candidateBox = worship_penatalayan_png_text_box($candidate, $fontSize, $fontPath);
            if ($candidateBox['width'] <= $maxWidth) {
                $currentLine = $candidate;
                continue;
            }

            if ($currentLine !== '') {
                $wrapped[] = $currentLine;
                $currentLine = '';
            }

            $wordBox = worship_penatalayan_png_text_box($word, $fontSize, $fontPath);
            if ($wordBox['width'] <= $maxWidth) {
                $currentLine = $word;
                continue;
            }

            $segments = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [$word];
            $chunk = '';
            foreach ($segments as $segment) {
                $testChunk = $chunk . $segment;
                $testBox = worship_penatalayan_png_text_box($testChunk, $fontSize, $fontPath);
                if ($testBox['width'] <= $maxWidth || $chunk === '') {
                    $chunk = $testChunk;
                    continue;
                }
                $wrapped[] = $chunk;
                $chunk = $segment;
            }
            if ($chunk !== '') {
                $currentLine = $chunk;
            }
        }

        if ($currentLine !== '') {
            $wrapped[] = $currentLine;
        }
    }

    return count($wrapped) > 0 ? $wrapped : [''];
}
