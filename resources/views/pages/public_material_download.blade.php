<?php

if ($page === 'public_material_download') {
    $menu = normalize_public_material_menu((string) ($_GET['menu'] ?? ''));
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($menu === '' || $id === '') {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }

    $allowedRows = church_files_for_public_material($churchFiles, $menu);
    $selected = null;
    foreach ($allowedRows as $row) {
        if ((string) ($row['id'] ?? '') === $id) {
            $selected = $row;
            break;
        }
    }
    if ($selected === null) {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }

    $path = sanitize_relative_upload_path((string) ($selected['path'] ?? ''));
    if ($path === '' || !is_upload_path($path)) {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }
    $fullPath = legacy_runtime_path($path);
    if (!is_file($fullPath)) {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }

    $fileName = trim((string) ($selected['file_name'] ?? basename($path)));
    if ($fileName === '') {
        $fileName = basename($path);
    }
    $downloadName = preg_replace('/[\\x00-\\x1F\\x7F"\\\\]+/', '_', $fileName) ?? $fileName;
    if ($downloadName === '') {
        $downloadName = 'materi';
    }
    $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'materi';
    if ($asciiDownloadName === '') {
        $asciiDownloadName = 'materi';
    }

    $ext = secure_file_extension($path);
    $contentType = secure_file_mime_by_extension($ext);
    if ($contentType === '') {
        $contentType = detect_file_mime_type($fullPath);
    }
    if ($contentType === '') {
        $contentType = 'application/octet-stream';
    }
    $contentLength = (int) @filesize($fullPath);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header('Content-Type: ' . $contentType);
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Download-Options: noopen');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Disposition: attachment; filename="' . $asciiDownloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    if ($contentLength > 0) {
        header('Content-Length: ' . (string) $contentLength);
    }

    $fp = fopen($fullPath, 'rb');
    if ($fp === false) {
        http_response_code(500);
        legacy_exit('Gagal membaca file.');
    }
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false) {
            break;
        }
        echo $chunk;
    }
    fclose($fp);
    legacy_exit();
}
