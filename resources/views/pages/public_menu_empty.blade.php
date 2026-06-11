<?php

if ($page === 'public_menu_empty') {
    page_header_plain('Menu Publik', $settings, 'page-public-menu');
    $menuRaw = trim((string) ($_GET['menu'] ?? ''));
    $menuLabels = [
        'materi_dg_1' => 'Materi DG-1 (BePI)',
        'materi_dg_2' => 'Materi DG-2 (BOI)',
        'materi_dg_3' => 'Materi DG-3',
        'meditasi_injil' => 'Meditasi Injil (BePI)',
        'jurnal_umpan_balik_anggota' => 'Jurnal Umpan Balik Anggota',
        'handbook_perjanjian_kelompok' => 'Handbook & Perjanjian Kelompok',
        'unggah_pertanyaan_sulit' => 'Unggah Pertanyaan Sulit',
        'jawaban_pertanyaan_sulit' => 'Jawaban Pertanyaan Sulit',
    ];
    $menuLabel = $menuLabels[$menuRaw] ?? 'Menu';

    echo "<section class=\"card public-empty-card\">\n";
    echo "  <h2>" . h($menuLabel) . "</h2>\n";
    echo "  <p>Halaman ini masih kosong dan akan diisi berikutnya.</p>\n";
    echo "  <div class=\"form-actions\">\n";
    echo "    <a class=\"btn ghost\" href=\"index.php\">Kembali ke Halaman Awal</a>\n";
    echo "  </div>\n";
    echo "</section>\n";
    page_footer_plain();
    legacy_exit();
}
