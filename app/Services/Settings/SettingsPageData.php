<?php

namespace App\Services\Settings;

use Illuminate\Http\Request;

class SettingsPageData
{
    /**
     * @return array<string, mixed>
     */
    public function forRequest(Request $request): array
    {
        return [
            'settings' => ['church_name' => app_church_name()],
            'currentUsername' => current_username(),
            'centralReadOnly' => function_exists('is_central_discipleship_readonly_session') && is_central_discipleship_readonly_session(),
            'developerAccessMode' => function_exists('is_developer_access_mode') && is_developer_access_mode(),
            'errorCode' => trim((string) $request->query('error', '')),
            'pwChanged' => $request->query->has('pw_changed'),
            'errorMessages' => [
                'missing_pw_field' => 'Isi semua kolom password.',
                'pw_mismatch' => 'Konfirmasi password tidak sama.',
                'pw_short' => 'Password baru minimal 6 karakter.',
                'pw_wrong' => 'Password saat ini salah.',
                'pw_save_failed' => 'Gagal menyimpan password, coba lagi.',
                'developer_access_password_disabled' => 'Keluar dari mode akses user sebelum mengubah password.',
            ],
        ];
    }
}
