<?php

if ($page === 'login') {
    page_header_plain('Login', $settings, 'page-login');
    $errorCode = trim((string) ($_GET['error'] ?? ''));
    $waitSeconds = max(0, (int) ($_GET['wait'] ?? 0));
    $expired = isset($_GET['expired']);
    $accountRemoved = isset($_GET['account_removed']);
    $churchName = trim((string) ($settings['church_name'] ?? CHURCH_NAME));
    if ($churchName === '') {
        $churchName = CHURCH_NAME;
    }
    $loginPillars = [
        [
            'eyebrow' => 'Firman',
            'title' => 'Berakar pada Injil',
            'description' => 'Setiap proses administrasi mendukung pertumbuhan rohani dan pengajaran yang sehat.',
        ],
        [
            'eyebrow' => 'Komunitas',
            'title' => 'Bertumbuh bersama',
            'description' => 'Data, jurnal, dan koordinasi pelayanan disatukan untuk membangun kehidupan bergereja.',
        ],
        [
            'eyebrow' => 'Pelayanan',
            'title' => 'Tertib dan siap pakai',
            'description' => 'Portal ini membantu tim bekerja rapi agar fokus pelayanan tetap terjaga.',
        ],
    ];
    if ($errorCode === 'locked') {
        render_alert('danger', 'Terlalu banyak percobaan login. Coba lagi dalam ' . format_lock_wait_label($waitSeconds) . '.');
    } elseif ($errorCode !== '') {
        render_alert('danger', 'Username atau password salah.');
    } elseif ($expired) {
        render_alert('danger', 'Sesi login berakhir karena tidak aktif. Silakan login kembali.');
    } elseif ($accountRemoved) {
        render_alert('danger', 'Akun ini sudah tidak aktif. Silakan login dengan akun yang tersedia.');
    }
    echo "<section class=\"login-shell\">\n";
    echo "  <aside class=\"login-brand-panel\">\n";
    echo "    <div class=\"login-brand-top\">\n";
    echo "      <span class=\"login-brand-tag\">Portal Internal REC</span>\n";
    echo "      <span class=\"login-brand-tag is-soft\">REC Indonesia</span>\n";
    echo "    </div>\n";
    echo "    <div class=\"login-brand-hero\">\n";
    echo "      <div class=\"login-brand-logo-wrap\">\n";
    echo "        <img src=\"/assets/logo.png\" alt=\"Logo " . h($churchName) . "\" decoding=\"async\">\n";
    echo "      </div>\n";
    echo "      <div class=\"login-brand-copy\">\n";
    echo "        <p class=\"login-brand-kicker\">" . h($churchName) . "</p>\n";
    echo "        <h1>Administrasi yang tertib untuk menopang pemuridan.</h1>\n";
    echo "        <p>Gunakan portal ini untuk mengelola data jemaat, jurnal pertemuan, dan kebutuhan pelayanan REC secara rapi.</p>\n";
    echo "      </div>\n";
    echo "    </div>\n";
    echo "    <div class=\"login-brand-grid\">\n";
    foreach ($loginPillars as $pillar) {
        $pillarEyebrow = trim((string) ($pillar['eyebrow'] ?? ''));
        $pillarTitle = trim((string) ($pillar['title'] ?? ''));
        $pillarDescription = trim((string) ($pillar['description'] ?? ''));
        echo "      <article class=\"login-brand-pillar\">\n";
        if ($pillarEyebrow !== '') {
            echo "        <span class=\"login-brand-pillar-eyebrow\">" . h($pillarEyebrow) . "</span>\n";
        }
        if ($pillarTitle !== '') {
            echo "        <strong class=\"login-brand-pillar-title\">" . h($pillarTitle) . "</strong>\n";
        }
        if ($pillarDescription !== '') {
            echo "        <p>" . h($pillarDescription) . "</p>\n";
        }
        echo "      </article>\n";
    }
    echo "    </div>\n";
    echo "    <div class=\"login-brand-footer\">\n";
    echo "      <span class=\"login-brand-badge\">Komunitas</span>\n";
    echo "      <span class=\"login-brand-badge\">Pemuridan</span>\n";
    echo "      <span class=\"login-brand-badge\">Pelayanan</span>\n";
    echo "    </div>\n";
    echo "  </aside>\n";
    echo "  <section class=\"card login-card\">\n";
    echo "    <div class=\"login-head\">\n";
    echo "      <div class=\"login-eyebrow\">Akses Admin</div>\n";
    echo "      <div class=\"login-title\">Masuk</div>\n";
    echo "      <div class=\"login-sub\">Gunakan akun untuk mengakses dashboard internal, data pemuridan, dan modul pelayanan REC.</div>\n";
    echo "    </div>\n";
    echo "    <form method=\"post\" class=\"form-grid login-form\">\n";
    echo "      <input type=\"hidden\" name=\"action\" value=\"login\">\n";
    echo "      <label class=\"login-field\">Username<input type=\"text\" name=\"username\" required autocomplete=\"username\" placeholder=\"Masukkan username\" autofocus spellcheck=\"false\"></label>\n";
    echo "      <label class=\"login-field\">Password<input type=\"password\" name=\"password\" required autocomplete=\"current-password\" placeholder=\"Masukkan password\"></label>\n";
    echo "      <div class=\"form-actions login-actions\">\n";
    echo "        <button class=\"btn\" type=\"submit\">Masuk</button>\n";
    echo "        <a class=\"btn ghost\" href=\"index.php\">Kembali</a>\n";
    echo "      </div>\n";
    echo "      <p class=\"login-note\">Halaman ini ditujukan untuk akun internal yang sudah terdaftar.</p>\n";
    echo "    </form>\n";
    echo "  </section>\n";
    echo "</section>\n";
    page_footer_plain();
    legacy_exit();
}
