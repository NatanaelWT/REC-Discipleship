<?php

function upload_church_file(array $file, string &$errorCode, string $folderPath = ''): ?array {
    $errorCode = '';
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        $errorCode = 'missing_church_file';
        return null;
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        $errorCode = 'church_file_upload_failed';
        return null;
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $errorCode = 'church_file_upload_failed';
        return null;
    }

    $maxBytes = 20 * 1024 * 1024;
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        $errorCode = 'church_file_too_large';
        return null;
    }

    $originalNameRaw = trim((string) ($file['name'] ?? ''));
    $originalName = basename(str_replace('\\', '/', $originalNameRaw));
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = [
        'pdf' => true,
        'doc' => true,
        'docx' => true,
        'xls' => true,
        'xlsx' => true,
        'ppt' => true,
        'pptx' => true,
        'txt' => true,
        'csv' => true,
        'jpg' => true,
        'jpeg' => true,
        'png' => true,
        'webp' => true,
        'zip' => true,
        'rar' => true,
    ];
    if ($ext === '' || !isset($allowedExtensions[$ext])) {
        $errorCode = 'invalid_church_file_type';
        return null;
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $tmpPath);
            if (is_string($detected)) {
                $mime = strtolower($detected);
            }
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $detected = mime_content_type($tmpPath);
        if (is_string($detected)) {
            $mime = strtolower($detected);
        }
    }
    $allowedMimes = [
        'application/pdf' => true,
        'application/msword' => true,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => true,
        'application/vnd.ms-excel' => true,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => true,
        'application/vnd.ms-powerpoint' => true,
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => true,
        'text/plain' => true,
        'text/csv' => true,
        'application/csv' => true,
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
        'application/zip' => true,
        'application/x-zip-compressed' => true,
        'application/x-rar' => true,
        'application/x-rar-compressed' => true,
        'application/vnd.rar' => true,
        'application/octet-stream' => true,
    ];
    if ($mime !== '' && !isset($allowedMimes[$mime])) {
        $errorCode = 'invalid_church_file_type';
        return null;
    }

    $folderPath = normalize_church_folder_path($folderPath);
    if (!ensure_church_folder_exists($folderPath)) {
        $errorCode = 'church_file_upload_failed';
        return null;
    }
    $targetDir = church_folder_full_path($folderPath);

    $filename = generate_id('file') . '_' . date('YmdHis') . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        $errorCode = 'church_file_upload_failed';
        return null;
    }

    if ($originalName === '') {
        $originalName = 'Dokumen.' . $ext;
    }

    return [
        'path' => church_folder_upload_relative_path($folderPath) . '/' . $filename,
        'name' => $originalName,
        'size' => $size,
        'mime' => $mime,
    ];
}
