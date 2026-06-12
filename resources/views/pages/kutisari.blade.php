<?php

if ($page === 'kutisari' || $page === 'public_links') {
    page_header_plain('Portal Publik', $settings, 'page-dg-public page-public-menu-home');
    $churchName = trim((string) ($settings['church_name'] ?? CHURCH_NAME));
    if ($churchName === '') {
        $churchName = CHURCH_NAME;
    }

    $publicMenuCards = [
        ['title' => 'Jurnal Temu DG', 'title_lines' => ['Jurnal', 'Temu DG'], 'sub' => '', 'href' => '/publik/jurnal-dg', 'is_primary' => true],
        ['title' => 'Jurnal Umpan Balik Anggota', 'title_lines' => ['Jurnal', 'Umpan Balik', 'Anggota'], 'sub' => '', 'href' => '?page=public_member_feedback_branch', 'is_primary' => true, 'cta' => 'Isi Jurnal'],
        ['title' => 'Materi DG-1', 'sub' => '(BePI)', 'href' => '/materi/materi_dg_1', 'is_primary' => false],
        ['title' => 'Materi DG-2', 'sub' => '(BOI)', 'href' => '/materi/materi_dg_2', 'is_primary' => false],
        ['title' => 'Materi DG-3', 'href' => '/materi/materi_dg_3', 'is_primary' => false],
        ['title' => 'Meditasi Injil', 'sub' => '(BePI)', 'href' => '/materi/meditasi_injil', 'is_primary' => false],
        ['title' => 'Handbook & Perjanjian Kelompok', 'title_lines' => ['Handbook &', 'Perjanjian', 'Kelompok'], 'sub' => '', 'href' => '/materi/handbook_perjanjian_kelompok', 'is_primary' => false],
        ['title' => 'Unggah Pertanyaan Sulit', 'title_lines' => ['Unggah', 'Pertanyaan', 'Sulit'], 'sub' => '', 'href' => '?page=public_difficult_question_submit', 'is_primary' => false, 'tile_class' => 'is-half'],
        ['title' => 'Jawaban Pertanyaan Sulit', 'title_lines' => ['Jawaban', 'Pertanyaan', 'Sulit'], 'sub' => '', 'href' => '?page=public_difficult_answer_lookup', 'is_primary' => false, 'tile_class' => 'is-half'],
    ];

    echo "<section class=\"card public-menu-card\">\n";
    echo "<div class=\"public-menu-shell\">\n";
    echo "  <div class=\"public-menu-head\">\n";
    echo "    <div class=\"public-menu-brand\">\n";
    echo "      <img src=\"/assets/logo.png\" alt=\"Logo " . h($churchName) . "\" loading=\"lazy\" decoding=\"async\">\n";
    echo "      <h1>" . h($churchName) . "</h1>\n";
    echo "      <p class=\"public-menu-tagline\">Website Manajemen Pemuridan REC Indonesia</p>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <div class=\"public-menu-grid\" role=\"navigation\" aria-label=\"Menu publik\">\n";
    foreach ($publicMenuCards as $menuCard) {
        $menuTitle = trim((string) ($menuCard['title'] ?? 'Menu'));
        $menuSub = trim((string) ($menuCard['sub'] ?? ''));
        $menuHref = trim((string) ($menuCard['href'] ?? '?'));
        $menuTitleLength = function_exists('mb_strlen') ? mb_strlen($menuTitle) : strlen($menuTitle);
        $isPrimaryMenu = !empty($menuCard['is_primary']);
        $menuTitleLines = $menuCard['title_lines'] ?? [];
        $tileClass = $isPrimaryMenu ? 'public-menu-tile is-primary' : 'public-menu-tile';
        $extraTileClass = trim((string) ($menuCard['tile_class'] ?? ''));
        if ($extraTileClass !== '') {
            $tileClass .= ' ' . $extraTileClass;
        }
        $tileCta = trim((string) ($menuCard['cta'] ?? ($isPrimaryMenu ? 'Pilih Cabang' : 'Buka Menu')));
        $titleClass = 'public-menu-tile-title';
        $titleMarkup = h($menuTitle);
        if ($menuTitleLength >= 24) {
            $titleClass .= ' is-xlong';
        } elseif ($menuTitleLength >= 18) {
            $titleClass .= ' is-long';
        }
        if (is_array($menuTitleLines)) {
            $safeTitleLines = [];
            foreach ($menuTitleLines as $titleLine) {
                $titleLine = trim((string) $titleLine);
                if ($titleLine !== '') {
                    $safeTitleLines[] = h($titleLine);
                }
            }
            if ($safeTitleLines !== []) {
                $titleMarkup = implode('<br>', $safeTitleLines);
            }
        }
        echo "    <a class=\"" . h($tileClass) . "\" href=\"" . h($menuHref) . "\">\n";
        echo "      <span class=\"public-menu-tile-eyebrow\">Menu Publik</span>\n";
        echo "      <span class=\"" . h($titleClass) . "\">" . $titleMarkup . "</span>\n";
        if ($menuSub !== '') {
            echo "      <span class=\"public-menu-tile-sub\">" . h($menuSub) . "</span>\n";
        }
        echo "      <span class=\"public-menu-tile-cta\">" . h($tileCta) . " <svg viewBox=\"0 0 20 20\" focusable=\"false\" aria-hidden=\"true\"><path d=\"M7 4l6 6-6 6\"/></svg></span>\n";
        echo "    </a>\n";
    }
    echo "  </div>\n";
    echo "</div>\n";
    echo "</section>\n";
    echo "<a class=\"public-login-fab\" href=\"?page=login\" title=\"Login Admin\" aria-label=\"Login Admin\">";
    echo "<span class=\"public-login-fab-icon\" aria-hidden=\"true\"><svg viewBox=\"0 0 24 24\" focusable=\"false\"><path d=\"M7.5 11V8a4.5 4.5 0 0 1 9 0v3\"/><rect x=\"5\" y=\"11\" width=\"14\" height=\"10\" rx=\"2\" ry=\"2\"/><path d=\"M12 15v3\"/></svg></span>";
    echo "</a>\n";
    echo "<div class=\"public-social-wrap\">\n";
    echo "  <div class=\"public-social-label\">Ikuti Kami</div>\n";
    echo "  <div class=\"public-social-row\">\n";
    echo "    <a class=\"public-social-link is-instagram\" href=\"https://rec.or.id\" target=\"_blank\" rel=\"noopener\">Website</a>\n";
    echo "    <span class=\"public-social-sep\" aria-hidden=\"true\"></span>\n";
    echo "    <a class=\"public-social-link is-youtube\" href=\"https://www.youtube.com/@RECIndonesia\" target=\"_blank\" rel=\"noopener\">YouTube</a>\n";
    echo "  </div>\n";
    echo "</div>\n";

    page_footer_plain();
    legacy_exit();
}
