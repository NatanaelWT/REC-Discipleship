<?php

function worship_penatalayan_svg_text(float $x, float $y, array $lines, array $options = []): string {
    $fontSize = max(8, (int) ($options['size'] ?? 14));
    $lineHeight = max(10, (float) ($options['line_height'] ?? ($fontSize + 4)));
    $anchor = (string) ($options['anchor'] ?? 'start');
    $weight = (string) ($options['weight'] ?? '400');
    $fill = (string) ($options['fill'] ?? '#111827');
    $family = (string) ($options['family'] ?? 'Arial, sans-serif');
    $xAttr = number_format($x, 2, '.', '');
    $yAttr = number_format($y, 2, '.', '');
    $text = '<text x="' . $xAttr . '" y="' . $yAttr . '" fill="' . worship_penatalayan_svg_escape($fill) . '" font-size="' . (string) $fontSize . '" font-weight="' . worship_penatalayan_svg_escape($weight) . '" font-family="' . worship_penatalayan_svg_escape($family) . '" text-anchor="' . worship_penatalayan_svg_escape($anchor) . '" dominant-baseline="hanging">';
    foreach ($lines as $index => $line) {
        $safeLine = worship_penatalayan_svg_escape($line !== '' ? $line : ' ');
        $dy = $index === 0 ? '0' : number_format($lineHeight, 2, '.', '');
        $text .= '<tspan x="' . $xAttr . '" dy="' . $dy . '">' . $safeLine . '</tspan>';
    }
    $text .= '</text>';
    return $text;
}
