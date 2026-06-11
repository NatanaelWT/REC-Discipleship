<?php

function worship_penatalayan_png_text_box(string $text, int $fontSize, string $fontPath): array {
    $content = $text !== '' ? $text : ' ';
    if ($fontPath !== '' && function_exists('imagettfbbox')) {
        $box = imagettfbbox($fontSize, 0, $fontPath, $content);
        if (is_array($box)) {
            $xs = [(int) $box[0], (int) $box[2], (int) $box[4], (int) $box[6]];
            $ys = [(int) $box[1], (int) $box[3], (int) $box[5], (int) $box[7]];
            return [
                'width' => max($xs) - min($xs),
                'height' => max($ys) - min($ys),
            ];
        }
    }
    return [
        'width' => imagefontwidth(3) * strlen($content),
        'height' => imagefontheight(3),
    ];
}
