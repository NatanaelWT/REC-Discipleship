<?php

function upload_managed_file(array $file, string &$errorCode, array $options): ?array
{
    $errorCode = '';
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $missingError = (string) ($options['missing_error'] ?? '');
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        $errorCode = $missingError;

        return null;
    }

    $uploadFailedError = (string) ($options['upload_failed_error'] ?? 'upload_failed');
    $oversizeError = (string) ($options['oversize_error'] ?? $uploadFailedError);
    if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $errorCode = $oversizeError;

        return null;
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        $errorCode = $uploadFailedError;

        return null;
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || ! is_uploaded_file($tmpPath)) {
        $errorCode = $uploadFailedError;

        return null;
    }

    $maxBytes = max(1, (int) ($options['max_bytes'] ?? 0));
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > $maxBytes) {
        $errorCode = $oversizeError;

        return null;
    }

    $mime = detect_file_mime_type($tmpPath);
    $allowedByMime = is_array($options['allowed_by_mime'] ?? null) ? $options['allowed_by_mime'] : [];
    $allowedByExtension = is_array($options['allowed_by_ext'] ?? null) ? $options['allowed_by_ext'] : [];
    $extension = (string) ($allowedByMime[$mime] ?? '');
    if ($extension === '' && $mime === 'application/octet-stream') {
        $originalExtension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $extension = (string) ($allowedByExtension[$originalExtension] ?? '');
    }
    if ($extension === '') {
        $errorCode = (string) ($options['invalid_type_error'] ?? $uploadFailedError);

        return null;
    }

    $imageMetadata = [];
    if (! empty($options['validate_image'])) {
        $dimensions = @getimagesize($tmpPath);
        $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : 0;
        $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : 0;
        $maxSide = max(1, (int) config('media.original_max_side', 20000));
        $maxPixels = max(1, (int) config('media.original_max_pixels', 100_000_000));
        if ($width < 1 || $height < 1 || max($width, $height) > $maxSide || ($width * $height) > $maxPixels) {
            $errorCode = (string) ($options['invalid_type_error'] ?? $uploadFailedError);

            return null;
        }
        $imageMetadata = ['width' => $width, 'height' => $height];
    }

    $sha256 = @hash_file('sha256', $tmpPath);
    if (! is_string($sha256) || $sha256 === '') {
        $errorCode = $uploadFailedError;

        return null;
    }

    $relativeDirectory = trim(str_replace('\\', '/', (string) ($options['relative_dir'] ?? '')), '/');
    if ($relativeDirectory === '') {
        $errorCode = $uploadFailedError;

        return null;
    }

    $targetDirectory = rec_runtime_path($relativeDirectory);
    if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
        $errorCode = $uploadFailedError;

        return null;
    }

    $prefix = sanitize_file_name_component((string) ($options['file_prefix'] ?? 'file'), 'file');
    $contentAddressed = ! empty($options['content_addressed']);
    $filename = $contentAddressed
        ? $prefix.'_'.strtolower($sha256).'.'.$extension
        : $prefix.'_'.bin2hex(random_bytes(12)).'.'.$extension;
    $targetPath = $targetDirectory.'/'.$filename;
    $storageReused = $contentAddressed && is_file($targetPath);
    if (! $storageReused) {
        if ($contentAddressed) {
            $temporaryPath = $targetDirectory.'/.'.$filename.'.'.bin2hex(random_bytes(6)).'.part';
            if (! move_uploaded_file($tmpPath, $temporaryPath)) {
                $errorCode = $uploadFailedError;

                return null;
            }
            if (@link($temporaryPath, $targetPath)) {
                @unlink($temporaryPath);
            } elseif (is_file($targetPath)) {
                @unlink($temporaryPath);
                $storageReused = true;
            } elseif (! @rename($temporaryPath, $targetPath)) {
                if (is_file($targetPath)) {
                    @unlink($temporaryPath);
                    $storageReused = true;
                } else {
                    @unlink($temporaryPath);
                    $errorCode = $uploadFailedError;

                    return null;
                }
            }
        } elseif (! move_uploaded_file($tmpPath, $targetPath)) {
            $errorCode = $uploadFailedError;

            return null;
        }
    }

    $originalName = basename(str_replace('\\', '/', trim((string) ($file['name'] ?? ''))));
    $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? '';
    if ($originalName === '') {
        $defaultName = trim((string) ($options['default_name'] ?? 'File')) ?: 'File';
        $originalName = ! empty($options['append_extension_to_default_name'])
            ? $defaultName.'.'.$extension
            : $defaultName;
    }

    $relativePath = $relativeDirectory.'/'.$filename;
    $result = [
        'path' => $relativePath,
        'name' => function_exists('mb_substr') ? mb_substr($originalName, 0, 255) : substr($originalName, 0, 255),
        'sha256' => strtolower($sha256),
        'size' => $size,
        'storage_reused' => $storageReused,
        ...$imageMetadata,
    ];

    $resultBuilder = $options['build_result'] ?? null;
    if (is_callable($resultBuilder)) {
        $extra = $resultBuilder($relativePath, $originalName, $mime, $extension, $size);
        if (is_array($extra)) {
            $result = array_merge($result, $extra);
        }
    }

    return $result;
}
