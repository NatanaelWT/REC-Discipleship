<?php

namespace App\Services\SecureFiles;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SecureFileStreamer
{
    public function stream(SecureFile $file): BinaryFileResponse
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
            'Vary' => 'Accept-Encoding',
        ];

        $response = new BinaryFileResponse($file->fullPath, 200, $headers, false, null, false, true);
        $response->setContentDisposition(
            $file->download ? 'attachment' : 'inline',
            $file->downloadName,
            $file->asciiDownloadName,
        );
        $response->setEtag(sha1($file->contentLength.':'.(string) @filemtime($file->fullPath)), true);

        return $response;
    }
}
