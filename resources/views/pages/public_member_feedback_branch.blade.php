<?php

if ($page === 'public_member_feedback_branch') {
    page_header_plain('Pilih Cabang Jurnal Umpan Balik Anggota', $settings, 'page-dg-public page-public-dg-branch page-public-member-feedback-branch');
    $errorCode = trim((string) ($_GET['error'] ?? ''));
    if ($errorCode === 'invalid_branch') {
        render_alert('danger', 'Cabang yang dipilih tidak valid. Silakan pilih cabang terlebih dahulu.');
    }
    $feedbackSessionParam = normalize_public_member_feedback_session($_GET['feedback_session'] ?? ($_GET['session'] ?? ''));

    $branchOptions = public_dg_branch_options();
    $branchCount = count($branchOptions);

    echo "<section class=\"card public-branch-select-card\">\n";
    echo "  <div class=\"public-branch-head\">\n";
    echo "    <div class=\"card-row public-branch-title-row\">\n";
    echo "      <div class=\"public-branch-title-wrap\">\n";
    echo "        <h2>Pilih Cabang Jurnal Umpan Balik Anggota</h2>\n";
    echo "        <p class=\"public-branch-subtitle\">Pilih cabang terlebih dahulu untuk membuka jurnal umpan balik anggota DG.</p>\n";
    echo "      </div>\n";
    echo "      <span class=\"badge warning\">Form Publik</span>\n";
    echo "    </div>\n";
    echo "    <div class=\"public-branch-meta\" role=\"status\" aria-live=\"polite\">\n";
    echo "      <span class=\"public-branch-count\">" . h((string) $branchCount) . " cabang aktif</span>\n";
    echo "      <span class=\"public-branch-divider\" aria-hidden=\"true\"></span>\n";
    echo "      <span class=\"public-branch-guide\">Pilih cabang di bawah</span>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <div class=\"public-branch-fieldset\">\n";
    echo "    <div class=\"public-branch-grid\">\n";
    foreach ($branchOptions as $branchOption) {
        $branchCode = normalize_public_branch_code((string) ($branchOption['code'] ?? 'kutisari'));
        $branchLabel = trim((string) ($branchOption['label'] ?? 'Kutisari'));
        if ($branchLabel === '') {
            $branchLabel = 'Kutisari';
        }
        $hrefParams = ['page' => 'public_member_feedback', 'cabang' => $branchCode];
        if ($feedbackSessionParam !== 0) {
            $hrefParams['feedback_session'] = $feedbackSessionParam;
        }
        echo "      <a class=\"public-branch-link-card\" href=\"?" . h(http_build_query($hrefParams)) . "\" aria-label=\"Isi jurnal umpan balik cabang " . h($branchLabel) . "\">\n";
        echo "        <span class=\"public-branch-card-eyebrow\">Cabang</span>\n";
        echo "        <span class=\"public-branch-card-title\">" . h($branchLabel) . "</span>\n";
        echo "        <span class=\"public-branch-card-cta\">Isi Jurnal <svg viewBox=\"0 0 20 20\" focusable=\"false\" aria-hidden=\"true\"><path d=\"M7 4l6 6-6 6\"/></svg></span>\n";
        echo "      </a>\n";
    }
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <div class=\"public-branch-actions actions\">\n";
    echo "    <a class=\"btn ghost\" href=\"index.php\">Kembali</a>\n";
    echo "  </div>\n";
    echo "</section>\n";
    page_footer_plain();
    legacy_exit();
}
