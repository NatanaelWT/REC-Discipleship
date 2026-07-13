<?php

function upload_member_photo(array $file, string &$errorCode): ?array
{
    return upload_managed_file($file, $errorCode, [
        'upload_failed_error' => 'member_photo_upload_failed',
        'oversize_error' => 'member_photo_too_large',
        'invalid_type_error' => 'invalid_member_photo_type',
        'max_bytes' => 5 * 1024 * 1024,
        'allowed_by_mime' => [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/x-png' => 'png',
            'image/webp' => 'webp',
        ],
        'allowed_by_ext' => [
            'jpg' => 'jpg',
            'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
        ],
        'relative_dir' => 'uploads/peserta',
        'file_prefix' => 'peserta',
        'default_name' => 'Foto',
        'append_extension_to_default_name' => true,
        'validate_image' => true,
        'content_addressed' => true,
    ]);
}
