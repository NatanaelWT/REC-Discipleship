<?php

namespace App\Services\PublicMaterials;

use App\Models\PublicMaterialFile;
use App\Support\RuntimeBootstrap;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicMaterialFileStreamer
{
    /**
     * @param  "inline"|"attachment"  $disposition
     */
    public function stream(PublicMaterialFile $file, string $disposition): BinaryFileResponse
    {
        RuntimeBootstrap::load();

        $path = sanitize_relative_upload_path((string) $file->relative_path);
        if ($path === '' || ! is_public_material_path($path)) {
            abort(404, 'File tidak ditemukan.');
        }

        $fullPath = public_material_resolve_path($path);
        if ($fullPath === null) {
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

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $headers = [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Download-Options' => 'noopen',
            'Cache-Control' => 'public, max-age=3600, stale-while-revalidate=86400',
            'Vary' => 'Accept-Encoding',
        ];

        $response = new BinaryFileResponse($fullPath, 200, $headers, true, null, false, true);
        $response->setContentDisposition($disposition, $downloadName, $asciiDownloadName);
        $checksum = strtolower(trim((string) $file->sha256));
        if (preg_match('/\A[a-f0-9]{64}\z/', $checksum) === 1) {
            $response->setEtag($checksum);
        } else {
            $response->setEtag($this->weakEntityTag($fullPath), true);
        }
        $response->isNotModified(request());

        return $response;
    }

    private function weakEntityTag(string $path): string
    {
        return sha1((string) @filesize($path).':'.(string) @filemtime($path));
    }
}
