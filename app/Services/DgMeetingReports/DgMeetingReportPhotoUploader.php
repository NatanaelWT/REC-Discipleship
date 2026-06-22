<?php

namespace App\Services\DgMeetingReports;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DgMeetingReportPhotoUploader
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    private const RELATIVE_DIRECTORY = 'uploads/dg_reports';

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{photos: array<int, array<string, string>>, error_message: string}
     */
    public function uploadFromRequest(Request $request): array
    {
        $files = $request->file('meeting_photos', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        if (! is_array($files) || $files === []) {
            return ['photos' => [], 'error_message' => ''];
        }

        $photos = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                $this->cleanup($photos);

                return ['photos' => [], 'error_message' => $this->messageForErrorCode('dg_photo_upload_failed')];
            }

            $upload = $this->uploadFile($file);
            if ($upload['error_code'] !== '') {
                $this->cleanup($photos);

                return ['photos' => [], 'error_message' => $this->messageForErrorCode($upload['error_code'])];
            }

            if ($upload['photo'] !== null) {
                $photos[] = $upload['photo'];
            }
        }

        return ['photos' => $photos, 'error_message' => ''];
    }

    /**
     * @param  array<int, array<string, string>>  $photos
     */
    public function cleanup(array $photos): void
    {
        cleanup_uploaded_entries($photos);
    }

    /**
     * @return array{photo: array<string, string>|null, error_code: string}
     */
    private function uploadFile(UploadedFile $file): array
    {
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return ['photo' => null, 'error_code' => 'dg_photo_too_large'];
        }
        if (! $file->isValid()) {
            return ['photo' => null, 'error_code' => 'dg_photo_upload_failed'];
        }

        $size = (int) ($file->getSize() ?: 0);
        if ($size < 1 || $size > self::MAX_FILE_SIZE) {
            return ['photo' => null, 'error_code' => 'dg_photo_too_large'];
        }

        $realPath = (string) ($file->getRealPath() ?: '');
        $mimeType = $realPath !== '' ? detect_file_mime_type($realPath) : '';
        if ($mimeType === 'application/octet-stream') {
            $mimeType = strtolower(trim((string) $file->getMimeType()));
        }
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? '';
        if ($extension === '') {
            return ['photo' => null, 'error_code' => 'invalid_dg_photo_type'];
        }

        $targetDirectory = rec_runtime_path(self::RELATIVE_DIRECTORY);
        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
            return ['photo' => null, 'error_code' => 'dg_photo_upload_failed'];
        }

        $filename = 'dg_'.strtolower((string) Str::ulid()).'.'.$extension;
        $relativePath = self::RELATIVE_DIRECTORY.'/'.$filename;

        try {
            $file->move($targetDirectory, $filename);
        } catch (Throwable $exception) {
            delete_relative_upload_file($relativePath);
            Log::warning('DG meeting photo could not be moved.', [
                'mime_type' => $mimeType,
                'size' => $size,
                'exception' => $exception,
            ]);

            return ['photo' => null, 'error_code' => 'dg_photo_upload_failed'];
        }

        return [
            'photo' => [
                'path' => $relativePath,
                'name' => $this->safeOriginalName($file, $extension),
            ],
            'error_code' => '',
        ];
    }

    private function safeOriginalName(UploadedFile $file, string $extension): string
    {
        $name = basename(str_replace('\\', '/', trim($file->getClientOriginalName())));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        if ($name === '') {
            return 'Foto.'.$extension;
        }

        return function_exists('mb_substr') ? mb_substr($name, 0, 255) : substr($name, 0, 255);
    }

    private function messageForErrorCode(string $errorCode): string
    {
        return match ($errorCode) {
            'invalid_dg_photo_type' => 'Format foto pertemuan tidak didukung. Gunakan JPG/PNG/WEBP.',
            'dg_photo_too_large' => 'Ukuran foto pertemuan terlalu besar. Maksimal 5 MB per file.',
            default => 'Upload foto pertemuan gagal. Coba ulangi lagi.',
        };
    }
}
