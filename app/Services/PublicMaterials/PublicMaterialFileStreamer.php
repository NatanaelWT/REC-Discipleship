<?php

namespace App\Services\PublicMaterials;

use App\Models\ChurchFile;
use App\Support\LegacyRuntimeBootstrap;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicMaterialFileStreamer
{
    /**
     * @param "inline"|"attachment" $disposition
     */
    public function stream(ChurchFile $file, string $disposition): StreamedResponse
    {
        LegacyRuntimeBootstrap::load();

        $path = sanitize_relative_upload_path((string) $file->relative_path);
        if ($path === '' || ! is_upload_path($path)) {
            abort(404, 'File tidak ditemukan.');
        }

        $fullPath = legacy_runtime_path($path);
        if (! is_file($fullPath)) {
            abort(404, 'File tidak ditemukan.');
        }

        $fileName = trim((string) ($file->original_file_name ?? basename($path)));
        if ($fileName === '') {
            $fileName = basename($path);
        }

        $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', $fileName) ?? $fileName;
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

        $headers = [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Download-Options' => 'noopen',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Content-Disposition' => $disposition . '; filename="' . $asciiDownloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName),
        ];
        if ($contentLength > 0) {
            $headers['Content-Length'] = (string) $contentLength;
        }

        return response()->stream(function () use ($fullPath): void {
            $fp = fopen($fullPath, 'rb');
            if ($fp === false) {
                return;
            }

            while (! feof($fp)) {
                $chunk = fread($fp, 8192);
                if ($chunk === false) {
                    break;
                }

                echo $chunk;
            }

            fclose($fp);
        }, 200, $headers);
    }
}
