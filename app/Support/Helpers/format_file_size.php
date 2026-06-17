<?php

function format_file_size(int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = (float) $bytes;
    $unitIndex = 0;
    $maxUnit = count($units) - 1;
    while ($size >= 1024 && $unitIndex < $maxUnit) {
        $size /= 1024;
        $unitIndex++;
    }
    if ($unitIndex === 0) {
        return (string) ((int) round($size)) . ' ' . $units[$unitIndex];
    }
    return number_format($size, 1, ',', '.') . ' ' . $units[$unitIndex];
}
