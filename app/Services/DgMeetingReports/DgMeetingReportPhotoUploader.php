<?php

namespace App\Services\DgMeetingReports;

class DgMeetingReportPhotoUploader
{
    /**
     * @return array{photos: array<int, array<string, string>>, error_message: string}
     */
    public function uploadFromPhpFiles(): array
    {
        $fileInput = $_FILES['meeting_photos'] ?? [];
        if (! is_array($fileInput) || ! array_key_exists('name', $fileInput)) {
            return ['photos' => [], 'error_message' => ''];
        }

        $errorCode = '';
        $photos = upload_dg_meeting_photos($fileInput, $errorCode);
        if ($errorCode === '') {
            return ['photos' => $photos, 'error_message' => ''];
        }

        return ['photos' => [], 'error_message' => $this->messageForErrorCode($errorCode)];
    }

    /**
     * @param array<int, array<string, string>> $photos
     */
    public function cleanup(array $photos): void
    {
        cleanup_uploaded_entries($photos);
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
