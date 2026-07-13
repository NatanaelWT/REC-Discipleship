<?php

function extract_member_photos(array $member): array {
    $photos = [];
    $seenPaths = [];
    $pushPhoto = function ($path, $name, array $metadata = []) use (&$photos, &$seenPaths): void {
        $safePath = sanitize_relative_upload_path((string) $path);
        if ($safePath === '' || isset($seenPaths[$safePath])) {
            return;
        }
        $label = trim((string) $name);
        if ($label === '') {
            $label = 'Foto';
        }
        $photo = [
            'path' => $safePath,
            'name' => $label,
        ];
        foreach (['web_path', 'thumbnail_path'] as $pathKey) {
            $variantPath = sanitize_relative_upload_path((string) ($metadata[$pathKey] ?? ''));
            if ($variantPath !== '') {
                $photo[$pathKey] = $variantPath;
            }
        }
        foreach (['sha256', 'variant_status'] as $key) {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value !== '') {
                $photo[$key] = $value;
            }
        }
        foreach (['size', 'width', 'height'] as $key) {
            $value = max(0, (int) ($metadata[$key] ?? 0));
            if ($value > 0) {
                $photo[$key] = $value;
            }
        }
        $photos[] = $photo;
        $seenPaths[$safePath] = true;
    };

    $rawPhotos = $member['photos'] ?? null;
    if (is_array($rawPhotos)) {
        foreach ($rawPhotos as $photoItem) {
            if (is_array($photoItem)) {
                $pushPhoto(
                    $photoItem['path'] ?? ($photoItem['photo_path'] ?? ''),
                    $photoItem['name'] ?? ($photoItem['photo_name'] ?? ''),
                    $photoItem,
                );
                continue;
            }
            if (is_string($photoItem)) {
                $pushPhoto($photoItem, '');
            }
        }
    }

    if (count($photos) === 0) {
        $singlePhotoPath = (string) ($member['photo_path'] ?? '');
        $singlePhotoName = (string) ($member['photo_name'] ?? '');
        $pushPhoto($singlePhotoPath, $singlePhotoName);
    }

    return $photos;
}
