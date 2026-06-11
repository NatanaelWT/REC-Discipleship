<?php

if ($page === 'secure_file') {
    $readQueryValue = function (string $key): string {
        $value = '';
        if (isset($_GET[$key]) && is_string($_GET[$key])) {
            $value = (string) $_GET[$key];
        } else {
            $ampKey = 'amp;' . $key;
            if (isset($_GET[$ampKey]) && is_string($_GET[$ampKey])) {
                $value = (string) $_GET[$ampKey];
            } else {
                $doubleAmpKey = 'amp;amp;' . $key;
                if (isset($_GET[$doubleAmpKey]) && is_string($_GET[$doubleAmpKey])) {
                    $value = (string) $_GET[$doubleAmpKey];
                }
            }
        }
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    };

    $pathRaw = $readQueryValue('path');
    $path = sanitize_relative_upload_path($pathRaw);
    if ($path === '' || !is_upload_path($path)) {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }
    if (is_logged_in() && !branch_can_access_secure_upload_path(current_user_branch(), $path)) {
        http_response_code(403);
        legacy_exit('Akses file tidak diizinkan.');
    }

    $uploadsRoot = realpath(legacy_runtime_path('uploads'));
    $fullPath = realpath(legacy_runtime_path($path));
    if ($uploadsRoot === false || $fullPath === false || !is_file($fullPath)) {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }

    $uploadsRootNormalized = rtrim(str_replace('\\', '/', $uploadsRoot), '/');
    $fullPathNormalized = str_replace('\\', '/', $fullPath);
    if (strpos($fullPathNormalized, $uploadsRootNormalized . '/') !== 0) {
        http_response_code(403);
        legacy_exit('Akses file tidak diizinkan.');
    }

    $ext = secure_file_extension($fullPath);
    $allowedExt = secure_file_allowed_extensions();
    if ($ext === '' || !isset($allowedExt[$ext])) {
        http_response_code(403);
        legacy_exit('Tipe file tidak diizinkan.');
    }

    $detectedMime = detect_file_mime_type($fullPath);
    $canonicalMime = secure_file_mime_by_extension($ext);
    if ($canonicalMime === '') {
        $canonicalMime = $detectedMime;
    }

    $downloadValue = $readQueryValue('download');
    $download = ($downloadValue === '1');
    $rawValue = $readQueryValue('raw');
    $raw = ($rawValue === '1');
    $inlineExt = secure_file_inline_extensions();
    if (!$download && !isset($inlineExt[$ext])) {
        $download = true;
    }

    $downloadName = trim($readQueryValue('name'));
    if ($downloadName === '') {
        $downloadName = basename($fullPath);
    }
    $downloadName = preg_replace('/[\\x00-\\x1F\\x7F"\\\\]+/', '_', $downloadName) ?? basename($fullPath);
    if ($downloadName === '') {
        $downloadName = basename($fullPath);
    }
    $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'file';
    if ($asciiDownloadName === '') {
        $asciiDownloadName = 'file';
    }
    $contentLength = (int) @filesize($fullPath);
    $scriptPath = trim((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptPath = str_replace('\\', '/', $scriptPath);
    if ($scriptPath === '' || substr($scriptPath, -4) !== '.php') {
        $scriptPath = 'index.php';
    } elseif ($scriptPath[0] !== '/') {
        $scriptPath = '/' . ltrim($scriptPath, '/');
    }

    // File responses do not need session writes; release lock so image requests can run in parallel.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if (!$download && !$raw && is_top_level_navigation_request()) {
        $rawUrl = $scriptPath . '?' . http_build_query([
            'page' => 'secure_file',
            'path' => $path,
            'raw' => '1',
        ]);
        $downloadUrl = $scriptPath . '?' . http_build_query([
            'page' => 'secure_file',
            'path' => $path,
            'download' => '1',
            'name' => $downloadName,
        ]);
        $previewTitle = trim($downloadName);
        if ($previewTitle === '') {
            $previewTitle = basename($fullPath);
        }
        if ($previewTitle === '') {
            $previewTitle = 'File';
        }
        $backUrl = '?page=dashboard';
        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer !== '' && is_same_origin_url($referer)) {
            $parsedReferer = @parse_url($referer);
            if (is_array($parsedReferer)) {
                $refererPath = trim((string) ($parsedReferer['path'] ?? ''));
                $refererQuery = trim((string) ($parsedReferer['query'] ?? ''));
                $refererFragment = trim((string) ($parsedReferer['fragment'] ?? ''));
                $refererPage = '';
                if ($refererQuery !== '') {
                    $queryParams = [];
                    parse_str($refererQuery, $queryParams);
                    if (is_array($queryParams)) {
                        $refererPage = trim((string) ($queryParams['page'] ?? ''));
                    }
                }
                if ($refererPath === '') {
                    $refererPath = $scriptPath;
                }
                if ($refererPath !== '' && $refererPage !== 'secure_file') {
                    $candidateBackUrl = $refererPath;
                    if ($refererQuery !== '') {
                        $candidateBackUrl .= '?' . $refererQuery;
                    }
                    if ($refererFragment !== '') {
                        $candidateBackUrl .= '#' . $refererFragment;
                    }
                    if ($candidateBackUrl !== '') {
                        $backUrl = $candidateBackUrl;
                    }
                }
            }
        }
        $backOnClick = "if (window.history.length > 1) { window.history.back(); return false; }";

        if ($ext === 'pdf') {
            page_header_plain('Preview PDF', $settings, 'page-file-preview-standalone');
            echo "<iframe class=\"file-page-embed\" src=\"" . h($rawUrl) . "\" loading=\"eager\" referrerpolicy=\"same-origin\" title=\"" . h($previewTitle) . "\"></iframe>\n";
            page_footer_plain();
            legacy_exit();
        }

        page_header_plain('Preview File', $settings, 'page-file-preview');
        echo "<section class=\"card\">\n";
        echo "  <div class=\"card-row\">\n";
        echo "    <h2>Preview File</h2>\n";
        echo "    <div class=\"actions\">\n";
        echo "      <a class=\"btn tiny secondary\" href=\"" . h($downloadUrl) . "\">Unduh</a>\n";
        echo "      <a class=\"btn tiny ghost\" href=\"" . h($backUrl) . "\" onclick=\"" . h($backOnClick) . "\">Kembali</a>\n";
        echo "    </div>\n";
        echo "  </div>\n";
        echo "  <div class=\"panel-note\">" . h($previewTitle) . "</div>\n";
        if (in_array($ext, ['jpg', 'png', 'webp', 'gif'], true)) {
            echo "  <div class=\"file-view-image-wrap\"><img class=\"file-view-image\" src=\"" . h($rawUrl) . "\" alt=\"" . h($previewTitle) . "\"></div>\n";
        } else {
            echo "  <div class=\"file-view-embed-wrap\"><iframe class=\"file-view-embed\" src=\"" . h($rawUrl) . "\" loading=\"lazy\" referrerpolicy=\"same-origin\"></iframe></div>\n";
        }
        echo "</section>\n";
        page_footer_plain();
        legacy_exit();
    }

    $responseMime = $canonicalMime !== '' ? $canonicalMime : 'application/octet-stream';
    header('Content-Type: ' . $responseMime);
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Download-Options: noopen');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $asciiDownloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
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
