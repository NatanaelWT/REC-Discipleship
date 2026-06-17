<?php

namespace App\Services\SecureFiles;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SecureFileStreamer
{
    public function stream(SecureFile $file): StreamedResponse
    {
        if (! is_readable($file->fullPath)) {
            throw new HttpException(500, 'Gagal membaca file.');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $headers = [
            'Content-Type' => $file->mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Download-Options' => 'noopen',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Content-Disposition' => ($file->download ? 'attachment' : 'inline')
                . '; filename="' . $file->asciiDownloadName . '"; filename*=UTF-8\'\'' . rawurlencode($file->downloadName),
        ];

        if ($file->contentLength > 0) {
            $headers['Content-Length'] = (string) $file->contentLength;
        }

        return response()->stream(function () use ($file): void {
            $handle = fopen($file->fullPath, 'rb');
            if ($handle === false) {
                return;
            }

            while (! feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) {
                    break;
                }

                echo $chunk;
            }

            fclose($handle);
        }, 200, $headers);
    }
}
