<?php

namespace App\Services\Settings;

use App\Support\RuntimeBootstrap;
use Illuminate\Http\Request;

class SettingsPageData
{
    /**
     * @return array<string, mixed>
     */
    public function forRequest(Request $request): array
    {
        RuntimeBootstrap::boot($request);

        return [
            'settings' => ['church_name' => CHURCH_NAME],
            'currentUsername' => current_username(),
            'centralReadOnly' => function_exists('is_central_discipleship_readonly_session') && is_central_discipleship_readonly_session(),
            'errorCode' => trim((string) $request->query('error', '')),
            'pwChanged' => $request->query->has('pw_changed'),
            'errorMessages' => [
                'missing_pw_field' => 'Isi semua kolom password.',
                'pw_mismatch' => 'Konfirmasi password tidak sama.',
                'pw_short' => 'Password baru minimal 6 karakter.',
                'pw_wrong' => 'Password saat ini salah.',
                'pw_save_failed' => 'Gagal menyimpan password, coba lagi.',
            ],
        ];
    }
}
