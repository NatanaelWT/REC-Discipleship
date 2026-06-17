<?php

function worship_penatalayan_png_text_layout(string $text, int $maxWidth, int $fontSize, string $fontPath, float $lineHeight, float $paragraphGap = 0.0): array {
    $paragraphs = preg_split("/\r\n?|\n/", trim($text)) ?: [''];
    $lines = [];
    $extraGaps = [];
    $lastParagraphIndex = count($paragraphs) - 1;

    foreach ($paragraphs as $paragraphIndex => $paragraph) {
        $wrappedParagraph = worship_penatalayan_png_wrap_lines((string) $paragraph, $maxWidth, $fontSize, $fontPath);
        foreach ($wrappedParagraph as $wrappedLine) {
            $lines[] = $wrappedLine;
        }
        if ($paragraphGap > 0 && $paragraphIndex < $lastParagraphIndex && count($wrappedParagraph) > 0) {
            $extraGaps[count($lines) - 1] = $paragraphGap;
        }
    }

    if (count($lines) === 0) {
        $lines = [''];
    }

    return [
        'lines' => $lines,
        'extra_gaps' => $extraGaps,
        'height' => (count($lines) * $lineHeight) + array_sum($extraGaps),
    ];
}
