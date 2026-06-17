<?php

function upload_dg_meeting_photo(array $file, string &$errorCode): ?array {
    return upload_managed_file($file, $errorCode, [
        'upload_failed_error' => 'dg_photo_upload_failed',
        'oversize_error' => 'dg_photo_too_large',
        'invalid_type_error' => 'invalid_dg_photo_type',
        'max_bytes' => 5 * 1024 * 1024,
        'allowed_by_mime' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
        'allowed_by_ext' => [
            'jpg' => 'jpg',
            'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
        ],
        'relative_dir' => 'uploads/dg_reports',
        'file_prefix' => 'dg',
        'default_name' => 'Foto',
        'append_extension_to_default_name' => true,
    ]);
}
