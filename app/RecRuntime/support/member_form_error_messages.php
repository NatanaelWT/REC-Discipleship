<?php

function member_form_error_messages(bool $includeLeftReason = false): array {
    $messages = [
        'missing_member_fields' => 'Nama lengkap dan jenis kelamin wajib diisi.',
        'invalid_member_birth_date' => 'Tanggal lahir tidak valid. Gunakan tanggal lengkap atau format dd-mm untuk tanggal-bulan saja.',
        'invalid_member_email' => 'Email jemaat tidak valid.',
        'invalid_member_social_link' => 'Link sosial media tidak valid.',
        'invalid_member' => 'Data jemaat tidak ditemukan.',
        'invalid_member_photo_type' => 'Format foto tidak didukung. Gunakan JPG/PNG/WEBP.',
        'member_photo_too_large' => 'Ukuran foto terlalu besar. Maksimal 5 MB per file.',
        'member_photo_upload_failed' => 'Upload foto gagal. Coba ulangi lagi.',
    ];
    if ($includeLeftReason) {
        $messages['missing_member_left_reason'] = 'Isi alasan jemaat keluar terlebih dahulu.';
    }
    return $messages;
}
