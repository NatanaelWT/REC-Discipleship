<?php

namespace App\Services\DgMeetingReports;

use App\Services\Media\ClientImageVariantStore;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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

    /** @var array<string, true> */
    private array $createdPaths = [];

    public function __construct(private readonly ClientImageVariantStore $variantStore) {}

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

        return [
            'photos' => $this->variantStore->attachFromRequest(
                $photos,
                $request,
                'meeting_photo_web_variants',
                'meeting_photo_thumbnails',
                self::RELATIVE_DIRECTORY,
            ),
            'error_message' => '',
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $photos
     */
    public function cleanup(array $photos): void
    {
        $this->variantStore->cleanupCreatedDerivatives();
        cleanup_uploaded_entries(array_values(array_filter(
            $photos,
            fn (array $photo): bool => isset($this->createdPaths[(string) ($photo['path'] ?? '')]),
        )));
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

        $dimensions = $realPath !== '' ? @getimagesize($realPath) : false;
        $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : 0;
        $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : 0;
        $maxSide = max(1, (int) config('media.original_max_side', 20000));
        $maxPixels = max(1, (int) config('media.original_max_pixels', 100_000_000));
        if ($width < 1 || $height < 1 || max($width, $height) > $maxSide || ($width * $height) > $maxPixels) {
            return ['photo' => null, 'error_code' => 'invalid_dg_photo_type'];
        }

        $sha256 = $realPath !== '' ? @hash_file('sha256', $realPath) : false;
        if (! is_string($sha256) || $sha256 === '') {
            return ['photo' => null, 'error_code' => 'dg_photo_upload_failed'];
        }

        $targetDirectory = rec_runtime_path(self::RELATIVE_DIRECTORY);
        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
            return ['photo' => null, 'error_code' => 'dg_photo_upload_failed'];
        }

        $filename = 'dg_'.strtolower($sha256).'.'.$extension;
        $relativePath = self::RELATIVE_DIRECTORY.'/'.$filename;

        $targetPath = $targetDirectory.'/'.$filename;
        if (! is_file($targetPath)) {
            $temporaryName = '.'.$filename.'.'.bin2hex(random_bytes(6)).'.part';
            $temporaryPath = $targetDirectory.'/'.$temporaryName;
            try {
                $file->move($targetDirectory, $temporaryName);
                if (@link($temporaryPath, $targetPath)) {
                    @unlink($temporaryPath);
                    $this->createdPaths[$relativePath] = true;
                } elseif (is_file($targetPath)) {
                    @unlink($temporaryPath);
                } elseif (@rename($temporaryPath, $targetPath)) {
                    $this->createdPaths[$relativePath] = true;
                } elseif (is_file($targetPath)) {
                    @unlink($temporaryPath);
                } else {
                    @unlink($temporaryPath);

                    return ['photo' => null, 'error_code' => 'dg_photo_upload_failed'];
                }
            } catch (Throwable $exception) {
                if (isset($temporaryPath) && is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
                Log::warning('DG meeting photo could not be moved.', [
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'exception' => $exception,
                ]);

                return ['photo' => null, 'error_code' => 'dg_photo_upload_failed'];
            }
        }

        return [
            'photo' => [
                'path' => $relativePath,
                'name' => $this->safeOriginalName($file, $extension),
                'sha256' => strtolower($sha256),
                'size' => $size,
                'width' => $width,
                'height' => $height,
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
