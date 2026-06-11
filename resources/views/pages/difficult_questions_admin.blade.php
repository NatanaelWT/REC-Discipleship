<?php

if ($page === 'difficult_questions_admin') {
    if (!can_manage_difficult_questions()) {
        redirect_to(branch_home_page(current_user_branch()), ['error' => 'access_denied']);
    }

    page_header('Pertanyaan Sulit', $settings, $page, false, 'page-difficult-questions-admin');

    render_condition_alerts([
        ['when' => isset($_GET['answered']), 'tone' => 'success', 'message' => 'Jawaban pertanyaan berhasil disimpan.'],
    ]);
    render_mapped_error_alert(trim((string) ($_GET['error'] ?? '')), [
        'missing_question' => 'Pertanyaan yang akan dijawab tidak ditemukan.',
        'missing_answer' => 'Isi jawaban terlebih dahulu.',
        'question_not_found' => 'Data pertanyaan tidak ditemukan.',
        'save_failed' => 'Jawaban gagal disimpan. Coba ulangi lagi.',
    ]);

    $difficultQuestionsDisplay = array_values(array_filter($difficultQuestions, static function ($row): bool {
        return is_array($row);
    }));
    usort($difficultQuestionsDisplay, static function ($a, $b): int {
        $aStatus = strtolower(trim((string) ($a['status'] ?? 'pending')));
        $bStatus = strtolower(trim((string) ($b['status'] ?? 'pending')));
        if ($aStatus !== $bStatus) {
            return $aStatus === 'pending' ? -1 : 1;
        }
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    $pendingQuestionCount = 0;
    $answeredQuestionCount = 0;
    foreach ($difficultQuestionsDisplay as $questionRow) {
        $questionStatus = strtolower(trim((string) ($questionRow['status'] ?? 'pending')));
        if ($questionStatus === 'answered') {
            $answeredQuestionCount++;
        } else {
            $pendingQuestionCount++;
        }
    }

    echo "<section class=\"card difficult-question-admin-hero\">\n";
    echo "  <div class=\"card-row\">\n";
    echo "    <div>\n";
    echo "      <span class=\"badge warning\">Admin</span>\n";
    echo "      <h2>Pertanyaan Sulit</h2>\n";
    echo "      <p class=\"panel-note\">Pantau pertanyaan dari halaman publik, lalu isi jawaban agar pengirim bisa membukanya dengan password yang mereka buat.</p>\n";
    echo "    </div>\n";
    echo "    <div class=\"actions table-tools\">\n";
    echo "      <span class=\"badge warning\">" . h((string) $pendingQuestionCount) . " menunggu</span>\n";
    echo "      <span class=\"badge success\">" . h((string) $answeredQuestionCount) . " dijawab</span>\n";
    echo "      <span class=\"badge muted\">" . h((string) count($difficultQuestionsDisplay)) . " total</span>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<section class=\"card difficult-question-admin-card\">\n";
    echo "  <div class=\"card-row\">\n";
    echo "    <h2>Daftar Pertanyaan</h2>\n";
    echo "    <span class=\"badge muted\">" . h((string) count($difficultQuestionsDisplay)) . " pertanyaan</span>\n";
    echo "  </div>\n";
    if (count($difficultQuestionsDisplay) === 0) {
        echo "  <div class=\"panel-note\">Belum ada pertanyaan sulit yang masuk.</div>\n";
    } else {
        echo "  <div class=\"difficult-question-admin-list\">\n";
        foreach ($difficultQuestionsDisplay as $questionRow) {
            $questionId = trim((string) ($questionRow['id'] ?? ''));
            $questionText = trim((string) ($questionRow['question'] ?? ''));
            $answerText = trim((string) ($questionRow['answer'] ?? ''));
            $askerName = trim((string) ($questionRow['asker_name'] ?? ''));
            $createdAt = format_datetime_id((string) ($questionRow['created_at'] ?? ''));
            $answeredAt = format_datetime_id((string) ($questionRow['answered_at'] ?? ''));
            $answeredBy = trim((string) ($questionRow['answered_by'] ?? ''));
            $questionStatus = strtolower(trim((string) ($questionRow['status'] ?? 'pending')));
            $statusLabel = difficult_question_status_label($questionStatus);
            $statusClass = $questionStatus === 'answered' ? 'badge success' : 'badge warning';
            if ($askerName === '') {
                $askerName = 'Anonim';
            }
            if ($questionText === '') {
                $questionText = '(Pertanyaan kosong)';
            }

            echo "    <article class=\"difficult-question-admin-item\">\n";
            echo "      <div class=\"difficult-question-admin-head\">\n";
            echo "        <div>\n";
            echo "          <strong>" . h($askerName) . "</strong>\n";
            echo "          <span>Dikirim: " . h($createdAt) . "</span>\n";
            echo "        </div>\n";
            echo "        <span class=\"" . h($statusClass) . "\">" . h($statusLabel) . "</span>\n";
            echo "      </div>\n";
            echo "      <div class=\"difficult-question-admin-question\">" . nl2br(h($questionText)) . "</div>\n";
            if ($answerText !== '') {
                echo "      <div class=\"difficult-question-admin-answer\">\n";
                echo "        <span>Jawaban terakhir" . ($answeredAt !== '-' ? ': ' . h($answeredAt) : '') . ($answeredBy !== '' ? ' oleh ' . h($answeredBy) : '') . "</span>\n";
                echo "        <div>" . nl2br(h($answerText)) . "</div>\n";
                echo "      </div>\n";
            }
            if ($questionId !== '') {
                echo "      <form method=\"post\" class=\"form-grid difficult-question-answer-form\">\n";
                echo "        <input type=\"hidden\" name=\"action\" value=\"save_difficult_question_answer\">\n";
                echo "        <input type=\"hidden\" name=\"id\" value=\"" . h($questionId) . "\">\n";
                echo "        <label>Jawaban<textarea name=\"answer_text\" rows=\"5\" maxlength=\"8000\" required placeholder=\"Tulis jawaban untuk pertanyaan ini...\">" . h($answerText) . "</textarea></label>\n";
                echo "        <div class=\"form-actions member-form-actions is-right\">\n";
                echo "          <button class=\"btn\" type=\"submit\">" . h($answerText === '' ? 'Simpan Jawaban' : 'Perbarui Jawaban') . "</button>\n";
                echo "        </div>\n";
                echo "      </form>\n";
            } else {
                echo "      <div class=\"panel-note\">Pertanyaan ini tidak memiliki ID, jadi belum bisa dijawab.</div>\n";
            }
            echo "    </article>\n";
        }
        echo "  </div>\n";
    }
    echo "</section>\n";

    page_footer();
    legacy_exit();
}
