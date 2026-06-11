<?php

function normalize_sheet_axis_format_payload($value, int $maxIndex): array {
    if (!is_array($value)) {
        return [];
    }
    $normalized = [];
    foreach ($value as $indexRaw => $style) {
        if (is_int($indexRaw)) {
            $index = $indexRaw;
        } elseif (is_string($indexRaw) && preg_match('/^\d+$/', $indexRaw) === 1) {
            $index = (int) $indexRaw;
        } else {
            continue;
        }
        if ($index < 0 || $index >= $maxIndex || !is_array($style)) {
            continue;
        }
        $bold = !empty($style['bold']);
        $align = strtolower(trim((string) ($style['align'] ?? '')));
        if (!in_array($align, ['left', 'center', 'right'], true)) {
            $align = '';
        }
        if (!$bold && $align === '') {
            continue;
        }
        $item = [];
        if ($bold) {
            $item['bold'] = true;
        }
        if ($align !== '') {
            $item['align'] = $align;
        }
        $normalized[(string) $index] = $item;
    }
    return $normalized;
}
