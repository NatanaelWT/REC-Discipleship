<?php

function detect_file_mime_type(string $fullPath): string {
    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detectedMime = @finfo_file($finfo, $fullPath);
            @finfo_close($finfo);
            if (is_string($detectedMime) && trim($detectedMime) !== '') {
                $mimeType = strtolower(trim($detectedMime));
            }
        }
    }
    if ($mimeType === '' && function_exists('mime_content_type')) {
        $detectedMime = @mime_content_type($fullPath);
        if (is_string($detectedMime) && trim($detectedMime) !== '') {
            $mimeType = strtolower(trim($detectedMime));
        }
    }
    if ($mimeType === '') {
        $mimeType = 'application/octet-stream';
    }
    return $mimeType;
}
