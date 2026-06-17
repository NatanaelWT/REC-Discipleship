<?php

namespace App\Services\SecureFiles;

class SecureFile
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $fullPath,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly bool $download,
        public readonly string $downloadName,
        public readonly string $asciiDownloadName,
        public readonly int $contentLength,
    ) {
    }

    public function isImage(): bool
    {
        return in_array($this->extension, ['jpg', 'png', 'webp', 'gif'], true);
    }

    public function isPdf(): bool
    {
        return $this->extension === 'pdf';
    }
}
