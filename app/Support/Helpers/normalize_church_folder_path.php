<?php

function normalize_church_folder_path(string $value): string {
    $value = str_replace('\\', '/', trim($value));
    $value = trim($value, '/');
    if ($value === '') {
        return '';
    }
    $segments = explode('/', $value);
    $cleanSegments = [];
    foreach ($segments as $segment) {
        $cleanSegment = normalize_church_folder_segment($segment);
        if ($cleanSegment === '') {
            return '';
        }
        $cleanSegments[] = $cleanSegment;
    }
    if (count($cleanSegments) === 0) {
        return '';
    }
    return implode('/', $cleanSegments);
}
