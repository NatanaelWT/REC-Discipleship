<?php

function upload_managed_file(array $file, string &$errorCode, array $options): ?array {
    $errorCode = '';
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $missingError = (string) ($options['missing_error'] ?? '');
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        if ($missingError !== '') {
            $errorCode = $missingError;
        }
        return null;
    }

    $uploadFailedError = (string) ($options['upload_failed_error'] ?? 'upload_failed');
    $oversizeError = (string) ($options['oversize_error'] ?? $uploadFailedError);
    if (!empty($options['map_ini_size_to_oversize']) && in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $errorCode = $oversizeError;
        return null;
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        $errorCode = $uploadFailedError;
        return null;
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $errorCode = $uploadFailedError;
        return null;
    }

    $maxBytes = max(1, (int) ($options['max_bytes'] ?? 0));
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        $errorCode = $oversizeError;
        return null;
    }

    $mime = detect_file_mime_type($tmpPath);
    $allowedByMime = is_array($options['allowed_by_mime'] ?? null) ? $options['allowed_by_mime'] : [];
    $allowedByExt = is_array($options['allowed_by_ext'] ?? null) ? $options['allowed_by_ext'] : [];
    $ext = $allowedByMime[$mime] ?? '';
    if ($ext === '') {
        $originalExt = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $ext = $allowedByExt[$originalExt] ?? '';
    }
    if ($ext === '') {
        $errorCode = (string) ($options['invalid_type_error'] ?? $uploadFailedError);
        return null;
    }

    $relativeDir = trim(str_replace('\\', '/', (string) ($options['relative_dir'] ?? '')), '/');
    if ($relativeDir === '') {
        $errorCode = $uploadFailedError;
        return null;
    }
    $targetDir = rec_runtime_path($relativeDir);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        $errorCode = $uploadFailedError;
        return null;
    }

    $filePrefix = trim((string) ($options['file_prefix'] ?? 'file'));
    if ($filePrefix === '') {
        $filePrefix = 'file';
    }
    $filename = generate_id($filePrefix) . '_' . date('YmdHis') . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        $errorCode = $uploadFailedError;
        return null;
    }

    $originalNameRaw = (string) ($file['name'] ?? '');
    $originalName = !empty($options['use_basename_name'])
        ? basename(str_replace('\\', '/', $originalNameRaw))
        : trim($originalNameRaw);
    if ($originalName === '') {
        $defaultName = (string) ($options['default_name'] ?? 'File');
        $originalName = !empty($options['append_extension_to_default_name']) ? ($defaultName . '.' . $ext) : $defaultName;
    }

    $relativePath = $relativeDir . '/' . $filename;
    $result = [
        'path' => $relativePath,
        'name' => $originalName,
    ];
    $resultBuilder = $options['build_result'] ?? null;
    if (is_callable($resultBuilder)) {
        $extra = $resultBuilder($relativePath, $originalName, $mime, $ext, $size);
        if (is_array($extra)) {
            $result = array_merge($result, $extra);
        }
    }

    return $result;
}
