<?php

if ($page === 'public_difficult_answer_lookup') {
    page_header_plain('Jawaban Pertanyaan Sulit', $settings, 'page-dg-public page-public-difficult-answer');
    $errorCode = trim((string) ($_GET['error'] ?? ''));
    if ($errorCode === 'password_required') {
        render_alert('danger', 'Masukkan password yang dibuat saat mengirim pertanyaan.');
    }

    $lookupHash = trim((string) ($_SESSION['difficult_answer_lookup_hash'] ?? ''));
    $hasLookup = isset($_GET['looked']) && $lookupHash !== '';
    $matchedQuestions = [];
    if ($hasLookup) {
        foreach ($difficultQuestions as $questionRow) {
            if (!is_array($questionRow)) {
                continue;
            }
            if (hash_equals(trim((string) ($questionRow['password_lookup'] ?? '')), $lookupHash)) {
                $matchedQuestions[] = $questionRow;
            }
        }
    }

    echo "<section class=\"card public-question-card\">\n";
    echo "  <div class=\"public-question-head\">\n";
    echo "    <span class=\"public-question-kicker\">Jawaban</span>\n";
    echo "    <h2>Jawaban Pertanyaan Sulit</h2>\n";
    echo "    <p>Masukkan password yang dibuat saat mengirim pertanyaan untuk melihat status dan jawaban.</p>\n";
    echo "  </div>\n";
    echo "  <form method=\"post\" class=\"form-grid public-question-form public-answer-lookup-form\">\n";
    echo "    <input type=\"hidden\" name=\"action\" value=\"lookup_difficult_answer\">\n";
    echo "    <div class=\"public-question-password-panel public-answer-password-panel\">\n";
    echo "      <div class=\"public-question-password-copy\"><strong>Password Pertanyaan</strong><span>Gunakan password yang sama seperti saat mengirim pertanyaan.</span></div>\n";
    echo "      <label class=\"public-question-field\">Password <span class=\"required-mark\">*</span><input type=\"password\" name=\"question_password\" minlength=\"4\" required autocomplete=\"current-password\"></label>\n";
    echo "    </div>\n";
    echo "    <div class=\"form-actions public-question-actions\">\n";
    echo "      <button class=\"btn\" type=\"submit\">Buka Jawaban</button>\n";
    echo "      <a class=\"btn ghost\" href=\"?page=public_difficult_question_submit\">Kirim Pertanyaan Baru</a>\n";
    echo "      <a class=\"btn ghost\" href=\"index.php\">Kembali</a>\n";
    echo "    </div>\n";
    echo "  </form>\n";
    echo "</section>\n";

    if ($hasLookup) {
        echo "<section class=\"card public-answer-results-card\">\n";
        echo "  <div class=\"card-row public-answer-results-head\">\n";
        echo "    <h2>Hasil Pencarian</h2>\n";
        echo "    <span class=\"badge muted\">" . h((string) count($matchedQuestions)) . " pertanyaan</span>\n";
        echo "  </div>\n";
        if (count($matchedQuestions) === 0) {
            echo "  <div class=\"panel-note\">Tidak ada pertanyaan dengan password tersebut.</div>\n";
        } else {
            echo "  <div class=\"public-answer-list\">\n";
            foreach ($matchedQuestions as $questionRow) {
                $questionText = trim((string) ($questionRow['question'] ?? ''));
                $answerText = trim((string) ($questionRow['answer'] ?? ''));
                $status = strtolower(trim((string) ($questionRow['status'] ?? 'pending')));
                $statusLabel = difficult_question_status_label($status);
                $createdDate = normalize_ymd_date(substr((string) ($questionRow['created_at'] ?? ''), 0, 10));
                $createdLabel = $createdDate !== '' ? format_indo_date($createdDate) : '-';
                $answeredDate = normalize_ymd_date(substr((string) ($questionRow['answered_at'] ?? ''), 0, 10));
                $answeredLabel = $answeredDate !== '' ? format_indo_date($answeredDate) : '';
                $statusClass = $status === 'answered' && $answerText !== '' ? 'success' : 'warning';

                echo "    <article class=\"public-answer-item\">\n";
                echo "      <div class=\"public-answer-item-head\"><span class=\"badge " . h($statusClass) . "\">" . h($statusLabel) . "</span><span class=\"public-answer-date\">Dikirim: " . h($createdLabel) . "</span></div>\n";
                echo "      <div class=\"public-answer-question\">" . nl2br(h($questionText)) . "</div>\n";
                if ($status === 'answered' && $answerText !== '') {
                    echo "      <div class=\"public-answer-response\"><strong>Jawaban</strong><div>" . nl2br(h($answerText)) . "</div>";
                    if ($answeredLabel !== '') {
                        echo "<span>Dijawab: " . h($answeredLabel) . "</span>";
                    }
                    echo "</div>\n";
                } else {
                    echo "      <div class=\"panel-note\">Pertanyaan ini belum dijawab oleh admin pusat.</div>\n";
                }
                echo "    </article>\n";
            }
            echo "  </div>\n";
        }
        echo "</section>\n";
    }

    page_footer_plain();
    legacy_exit();
}
