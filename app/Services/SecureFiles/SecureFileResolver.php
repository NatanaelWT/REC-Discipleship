<?php

namespace App\Services\SecureFiles;

use App\Http\Requests\SecureFiles\ShowSecureFileRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SecureFileResolver
{
    public function resolve(ShowSecureFileRequest $request): SecureFile
    {
        $path = sanitize_relative_upload_path($request->filePath());
        if ($path === '' || ! is_upload_path($path)) {
            throw new HttpException(404, 'File tidak ditemukan.');
        }

        if (is_logged_in() && ! branch_can_access_secure_upload_path(current_user_branch(), $path)) {
            throw new HttpException(403, 'Akses file tidak diizinkan.');
        }

        $uploadsRoot = realpath(rec_runtime_path('uploads'));
        $fullPath = realpath(rec_runtime_path($path));
        if ($uploadsRoot === false || $fullPath === false || ! is_file($fullPath)) {
            throw new HttpException(404, 'File tidak ditemukan.');
        }

        $uploadsRootNormalized = rtrim(str_replace('\\', '/', $uploadsRoot), '/');
        $fullPathNormalized = str_replace('\\', '/', $fullPath);
        if (! str_starts_with($fullPathNormalized, $uploadsRootNormalized . '/')) {
            throw new HttpException(403, 'Akses file tidak diizinkan.');
        }

        $extension = secure_file_extension($fullPath);
        $allowedExtensions = secure_file_allowed_extensions();
        if ($extension === '' || ! isset($allowedExtensions[$extension])) {
            throw new HttpException(403, 'Tipe file tidak diizinkan.');
        }

        $mimeType = secure_file_mime_by_extension($extension);
        if ($mimeType === '') {
            $mimeType = detect_file_mime_type($fullPath);
        }
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        $download = $request->downloadRequested();
        $inlineExtensions = secure_file_inline_extensions();
        if (! $download && ! isset($inlineExtensions[$extension])) {
            $download = true;
        }

        $downloadName = $this->downloadName($request->requestedDownloadName(), $fullPath);
        $asciiDownloadName = $this->asciiDownloadName($downloadName);
        $contentLength = (int) @filesize($fullPath);

        return new SecureFile(
            relativePath: $path,
            fullPath: $fullPath,
            extension: $extension,
            mimeType: $mimeType,
            download: $download,
            downloadName: $downloadName,
            asciiDownloadName: $asciiDownloadName,
            contentLength: $contentLength,
        );
    }

    private function downloadName(string $requestedName, string $fullPath): string
    {
        $downloadName = trim($requestedName);
        if ($downloadName === '') {
            $downloadName = basename($fullPath);
        }

        $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', $downloadName) ?? basename($fullPath);
        if ($downloadName === '') {
            $downloadName = basename($fullPath);
        }

        return $downloadName;
    }

    private function asciiDownloadName(string $downloadName): string
    {
        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'file';

        return $asciiDownloadName !== '' ? $asciiDownloadName : 'file';
    }
}
