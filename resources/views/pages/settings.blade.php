<?php

if ($page === 'settings') {
    page_header('Pengaturan', $settings, $page);
    $pwChanged = isset($_GET['pw_changed']);
    $settingsError = trim((string) ($_GET['error'] ?? ''));
    $centralReadOnly = is_central_discipleship_readonly_session();
    if ($pwChanged) {
        render_alert('success', 'Password berhasil diubah.');
    } elseif ($settingsError !== '') {
        $errorMessages = [
            'missing_pw_field' => 'Isi semua kolom password.',
            'pw_mismatch' => 'Konfirmasi password tidak sama.',
            'pw_short' => 'Password baru minimal 6 karakter.',
            'pw_wrong' => 'Password saat ini salah.',
            'pw_save_failed' => 'Gagal menyimpan password, coba lagi.',
        ];
        $message = $errorMessages[$settingsError] ?? 'Terjadi kesalahan.';
        render_alert('danger', $message);
    }
    echo "<section class=\"card settings-account-card\">\n";
    echo "  <div class=\"settings-account-hero\">\n";
    echo "    <div class=\"settings-account-copy\">\n";
    echo "      <span class=\"settings-account-kicker\">Akun</span>\n";
    echo "      <h2>Kelola Password</h2>\n";
    echo "      <p>Kunci akun kamu agar tetap aman. Gunakan kombinasi huruf, angka, dan simbol untuk password yang kuat.</p>\n";
    echo "    </div>\n";
    echo "    <div class=\"settings-account-meta\">\n";
    echo "      <span class=\"settings-account-badge\">Username: " . h(current_username()) . "</span>\n";
    if ($centralReadOnly) {
        echo "      <span class=\"settings-account-badge is-muted\">Mode pusat · hanya lihat</span>\n";
    } else {
        echo "      <span class=\"settings-account-badge is-safe\">Data terenkripsi</span>\n";
    }
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <form method=\"post\" class=\"settings-account-form\">\n";
    echo "    <input type=\"hidden\" name=\"action\" value=\"change_password\">\n";
    echo "    <div class=\"settings-account-grid\">\n";
    echo "      <label class=\"settings-account-field-card is-current\">\n";
    echo "        <span class=\"settings-account-field-top\">\n";
    echo "          <span class=\"settings-account-field-eyebrow\">Langkah 1</span>\n";
    echo "          <span class=\"settings-account-field-preview\">Rahasiakan</span>\n";
    echo "        </span>\n";
    echo "        <span class=\"settings-account-field-title\">Password Sekarang</span>\n";
    echo "        <span class=\"settings-account-field-hint\">Masukkan password yang kamu pakai saat ini.</span>\n";
    echo "        <input type=\"password\" name=\"current_password\" autocomplete=\"current-password\" required>\n";
    echo "      </label>\n";
    echo "      <label class=\"settings-account-field-card is-new\">\n";
    echo "        <span class=\"settings-account-field-top\">\n";
    echo "          <span class=\"settings-account-field-eyebrow\">Langkah 2</span>\n";
    echo "          <span class=\"settings-account-field-preview\">6+ karakter</span>\n";
    echo "        </span>\n";
    echo "        <span class=\"settings-account-field-title\">Password Baru</span>\n";
    echo "        <span class=\"settings-account-field-hint\">Gunakan kombinasi huruf besar/kecil, angka, dan simbol.</span>\n";
    echo "        <input type=\"password\" name=\"new_password\" autocomplete=\"new-password\" required minlength=\"6\">\n";
    echo "      </label>\n";
    echo "      <label class=\"settings-account-field-card is-confirm\">\n";
    echo "        <span class=\"settings-account-field-top\">\n";
    echo "          <span class=\"settings-account-field-eyebrow\">Langkah 3</span>\n";
    echo "          <span class=\"settings-account-field-preview\">Cocok</span>\n";
    echo "        </span>\n";
    echo "        <span class=\"settings-account-field-title\">Konfirmasi Password Baru</span>\n";
    echo "        <span class=\"settings-account-field-hint\">Ketik ulang untuk memastikan tidak ada salah ketik.</span>\n";
    echo "        <input type=\"password\" name=\"new_password_confirm\" autocomplete=\"new-password\" required minlength=\"6\">\n";
    echo "      </label>\n";
    echo "    </div>\n";
    echo "    <div class=\"settings-account-actions\">\n";
    echo "      <button class=\"btn\" type=\"submit\"" . ($centralReadOnly ? " disabled aria-disabled=\"true\"" : "") . ">Ubah Password</button>\n";
    echo "    </div>\n";
    echo "  </form>\n";
    echo "</section>\n";
    page_footer();
    legacy_exit();
}
