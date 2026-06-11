<?php

function extract_member_photos(array $member): array {
    $photos = [];
    $seenPaths = [];
    $pushPhoto = function ($path, $name) use (&$photos, &$seenPaths): void {
        $safePath = sanitize_relative_upload_path((string) $path);
        if ($safePath === '' || isset($seenPaths[$safePath])) {
            return;
        }
        $label = trim((string) $name);
        if ($label === '') {
            $label = 'Foto';
        }
        $photos[] = [
            'path' => $safePath,
            'name' => $label,
        ];
        $seenPaths[$safePath] = true;
    };

    $rawPhotos = $member['photos'] ?? null;
    if (is_array($rawPhotos)) {
        foreach ($rawPhotos as $photoItem) {
            if (is_array($photoItem)) {
                $pushPhoto($photoItem['path'] ?? ($photoItem['photo_path'] ?? ''), $photoItem['name'] ?? ($photoItem['photo_name'] ?? ''));
                continue;
            }
            if (is_string($photoItem)) {
                $pushPhoto($photoItem, '');
            }
        }
    }

    if (count($photos) === 0) {
        $legacyPath = (string) ($member['photo_path'] ?? '');
        $legacyName = (string) ($member['photo_name'] ?? '');
        $pushPhoto($legacyPath, $legacyName);
    }

    return $photos;
}
