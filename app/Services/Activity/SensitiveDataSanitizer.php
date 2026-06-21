<?php

namespace App\Services\Activity;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Stringable;

class SensitiveDataSanitizer
{
    private const REDACTED = '[REDACTED]';

    public function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if ($value instanceof UploadedFile) {
            return $this->uploadedFileMetadata($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $childKey => $childValue) {
                $clean[$childKey] = $this->sanitize($childValue, is_string($childKey) ? $childKey : null);
            }

            return $clean;
        }

        if (is_object($value)) {
            if ($value instanceof Stringable) {
                return (string) $value;
            }

            return $this->sanitize((array) $value, $key);
        }

        if (is_resource($value)) {
            return '[RESOURCE]';
        }

        return $value;
    }

    public function isSensitiveKey(string $key): bool
    {
        $key = Str::lower(trim($key));

        return preg_match('/(^|[._-])(password|passwd|passphrase|token|secret|authorization|cookie|remember|csrf|hash)([._-]|$)/', $key) === 1
            || str_contains($key, 'password')
            || str_contains($key, 'token')
            || str_contains($key, 'secret');
    }

    /** @return array<string, mixed> */
    private function uploadedFileMetadata(UploadedFile $file): array
    {
        $path = $file->isValid() ? $file->getRealPath() : false;

        return [
            'original_name' => $file->getClientOriginalName(),
            'size_bytes' => max(0, (int) ($file->getSize() ?? 0)),
            'mime_type' => $file->getClientMimeType(),
            'sha256' => is_string($path) && is_file($path) ? hash_file('sha256', $path) : null,
            'upload_valid' => $file->isValid(),
        ];
    }
}
