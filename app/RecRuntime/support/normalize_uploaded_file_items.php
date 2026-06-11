<?php

function normalize_uploaded_file_items(array $fileInput): array {
    $names = $fileInput['name'] ?? null;
    if ($names === null) {
        return [];
    }

    if (!is_array($names)) {
        $single = [
            'name' => (string) ($fileInput['name'] ?? ''),
            'type' => (string) ($fileInput['type'] ?? ''),
            'tmp_name' => (string) ($fileInput['tmp_name'] ?? ''),
            'error' => (int) ($fileInput['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($fileInput['size'] ?? 0),
        ];
        if ($single['error'] === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        return [$single];
    }

    $items = [];
    foreach ($names as $idx => $nameValue) {
        if (is_array($nameValue)) {
            continue;
        }
        $item = [
            'name' => (string) $nameValue,
            'type' => (string) ($fileInput['type'][$idx] ?? ''),
            'tmp_name' => (string) ($fileInput['tmp_name'][$idx] ?? ''),
            'error' => (int) ($fileInput['error'][$idx] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($fileInput['size'][$idx] ?? 0),
        ];
        if ($item['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $items[] = $item;
    }
    return $items;
}
