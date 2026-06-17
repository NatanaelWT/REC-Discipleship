<?php

function worship_penatalayan_png_draw_text($image, array $lines, float $x, float $y, int $color, array $options = []): void {
    $anchor = (string) ($options['anchor'] ?? 'start');
    $fontSize = max(8, (int) ($options['size'] ?? 14));
    $lineHeight = max(10, (float) ($options['line_height'] ?? ($fontSize + 4)));
    $fontPath = (string) ($options['font'] ?? '');
    $fallbackFont = (int) ($options['fallback_font'] ?? 3);
    $extraGaps = is_array($options['extra_gaps'] ?? null) ? $options['extra_gaps'] : [];
    $currentY = $y;

    foreach ($lines as $lineIndex => $line) {
        $text = $line !== '' ? $line : ' ';
        if ($fontPath !== '' && function_exists('imagettftext')) {
            $box = worship_penatalayan_png_text_box($text, $fontSize, $fontPath);
            $drawX = $anchor === 'middle' ? (int) round($x - ($box['width'] / 2)) : (int) round($x);
            $drawY = (int) round($currentY + $box['height']);
            imagettftext($image, $fontSize, 0, $drawX, $drawY, $color, $fontPath, $text);
        } else {
            $drawX = $anchor === 'middle'
                ? (int) round($x - ((imagefontwidth($fallbackFont) * strlen($text)) / 2))
                : (int) round($x);
            imagestring($image, $fallbackFont, $drawX, (int) round($currentY), $text, $color);
        }
        $currentY += $lineHeight + (float) ($extraGaps[$lineIndex] ?? 0);
    }
}
