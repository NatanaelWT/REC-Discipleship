<?php

function worship_penatalayan_svg_wrap_lines(string $text, int $maxChars): array {
    $normalized = preg_replace("/\r\n?/", "\n", trim($text));
    if (!is_string($normalized) || $normalized === '') {
        return [''];
    }
    $lines = [];
    foreach (explode("\n", $normalized) as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            $lines[] = '';
            continue;
        }
        while (strlen($paragraph) > $maxChars) {
            $window = substr($paragraph, 0, $maxChars + 1);
            $breakPos = strrpos($window, ' ');
            if ($breakPos === false || $breakPos < max(4, (int) floor($maxChars * 0.5))) {
                $breakPos = $maxChars;
            }
            $lines[] = trim(substr($paragraph, 0, $breakPos));
            $paragraph = trim(substr($paragraph, $breakPos));
        }
        $lines[] = $paragraph;
    }
    return count($lines) > 0 ? $lines : [''];
}
