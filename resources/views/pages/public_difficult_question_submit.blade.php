<?php

if ($page === 'public_difficult_question_submit') {
    page_header_plain('Unggah Pertanyaan Sulit', $settings, 'page-dg-public page-public-difficult-question');
    $old = is_array($_SESSION['difficult_question_old'] ?? null) ? $_SESSION['difficult_question_old'] : [];
    $errorCode = trim((string) ($_GET['error'] ?? ''));
    $errorMessages = [
        'missing_question' => 'Isi pertanyaan terlebih dahulu.',
        'password_short' => 'Password minimal 4 karakter.',
        'password_mismatch' => 'Konfirmasi password tidak sama.',
        'save_failed' => 'Pertanyaan gagal disimpan. Coba ulangi lagi.',
    ];
    if (isset($_GET['submitted'])) {
        render_alert('success', 'Pertanyaan berhasil dikirim. Simpan password yang Anda buat untuk melihat jawaban nanti.');
    }
    if ($errorCode !== '' && isset($errorMessages[$errorCode])) {
        render_alert('danger', $errorMessages[$errorCode]);
    }

    echo "<section class=\"card public-question-card\">\n";
    echo "  <div class=\"public-question-head\">\n";
    echo "    <span class=\"public-question-kicker\">Pertanyaan Sulit</span>\n";
    echo "    <h2>Unggah Pertanyaan Sulit</h2>\n";
    echo "    <p>Buat password pribadi saat mengirim pertanyaan. Password ini dipakai untuk membuka jawaban setelah admin pusat menjawab.</p>\n";
    echo "  </div>\n";
    echo "  <form method=\"post\" class=\"form-grid public-question-form\">\n";
    echo "    <input type=\"hidden\" name=\"action\" value=\"submit_difficult_question\">\n";
    echo "    <label class=\"public-question-field\">Nama (opsional)<input type=\"text\" name=\"asker_name\" maxlength=\"120\" value=\"" . h((string) ($old['asker_name'] ?? '')) . "\" placeholder=\"Boleh dikosongkan\"></label>\n";
    echo "    <label class=\"public-question-field public-question-field-full\">Pertanyaan <span class=\"required-mark\">*</span><textarea name=\"question_text\" rows=\"7\" maxlength=\"6000\" required placeholder=\"Tulis pertanyaan yang ingin dijawab...\">" . h((string) ($old['question_text'] ?? '')) . "</textarea></label>\n";
    echo "    <div class=\"public-question-password-panel\">\n";
    echo "      <div class=\"public-question-password-copy\"><strong>Password Jawaban</strong><span>Simpan password ini. Password diperlukan untuk membuka jawaban Anda nanti.</span></div>\n";
    echo "      <div class=\"public-question-password-fields\">\n";
    echo "        <label class=\"public-question-field\">Password <span class=\"required-mark\">*</span><input type=\"password\" name=\"question_password\" minlength=\"4\" required autocomplete=\"new-password\"></label>\n";
    echo "        <label class=\"public-question-field\">Ulangi Password <span class=\"required-mark\">*</span><input type=\"password\" name=\"question_password_confirm\" minlength=\"4\" required autocomplete=\"new-password\"></label>\n";
    echo "      </div>\n";
    echo "    </div>\n";
    echo "    <div class=\"form-actions public-question-actions\">\n";
    echo "      <button class=\"btn\" type=\"submit\">Kirim Pertanyaan</button>\n";
    echo "      <a class=\"btn ghost\" href=\"?page=public_difficult_answer_lookup\">Lihat Jawaban</a>\n";
    echo "      <a class=\"btn ghost\" href=\"index.php\">Kembali</a>\n";
    echo "    </div>\n";
    echo "  </form>\n";
    echo "</section>\n";
    page_footer_plain();
    legacy_exit();
}
