<?php

function render_pemuridan_import_feedback(): void {
    if (isset($_GET['imported'])) {
        $importMskInserted = max(0, (int) ($_GET['import_msk_inserted'] ?? 0));
        $importMskUpdated = max(0, (int) ($_GET['import_msk_updated'] ?? 0));
        $importErrorCount = max(0, (int) ($_GET['import_error_count'] ?? 0));
        $importSummary = 'Import selesai. Kelas MSK: ' . $importMskInserted . ' tambah, ' . $importMskUpdated . ' update.';
        if ($importErrorCount > 0) {
            $importSummary .= ' Ada ' . $importErrorCount . ' baris gagal.';
        }
        render_alert('success', $importSummary);
        $importPreview = trim((string) ($_GET['import_error_preview'] ?? ''));
        if ($importErrorCount > 0 && $importPreview !== '') {
            render_alert('danger', 'Contoh error: ' . $importPreview);
        }
    }

    $error = trim((string) ($_GET['error'] ?? ''));
    $errorMessages = [
        'import_missing_file' => 'Pilih file Excel (.xlsx) untuk import pemuridan.',
        'import_upload_failed' => 'Upload file import gagal. Coba ulangi lagi.',
        'import_invalid_file_type' => 'Format file tidak didukung. Gunakan file Excel (.xlsx).',
        'import_file_too_large' => 'Ukuran file terlalu besar. Maksimal 10 MB.',
        'import_zip_unavailable' => 'Fitur import Excel belum tersedia di server (ekstensi ZipArchive belum aktif).',
        'import_invalid_excel' => 'File Excel tidak valid atau rusak.',
        'import_missing_sheet' => 'Sheet wajib tidak ditemukan. Pastikan ada sheet "Kelas MSK".',
        'import_empty_sheet' => 'Sheet Kelas MSK kosong. Isi minimal 1 baris data.',
    ];
    if (isset($errorMessages[$error])) {
        render_alert('danger', $errorMessages[$error]);
    }
}
