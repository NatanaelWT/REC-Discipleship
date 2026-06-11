<?php

if ($page === 'discipleship_targets') {
    page_header('Target DG & MSK', $settings, $page, false);
    $saved = isset($_GET['saved']);
    $centralReadOnly = is_effective_central_discipleship_readonly();
    if ($saved && !$centralReadOnly) {
        render_alert('success', 'Target DG & MSK berhasil disimpan.');
    }

    if ($centralReadOnly) {
        echo "<section class=\"card\">\n";
        echo "  <div class=\"card-row\">\n";
        echo "    <h2>Target DG & MSK Semua Cabang</h2>\n";
        echo "    <span class=\"badge muted\">Mode lihat saja (Pusat)</span>\n";
        echo "  </div>\n";
        echo "  <div class=\"table-wrap\">\n";
        echo "    <table class=\"table\">\n";
        echo "      <thead><tr><th>Cabang</th><th>Target Peserta Kamp GAP</th><th>Target Selesai MSK</th><th>Target Selesai DG 1</th><th>Target Selesai DG 2</th><th>Target Selesai DG 3</th></tr></thead>\n";
        echo "      <tbody>\n";
        foreach (public_dg_branch_options() as $branchOption) {
            $branchCode = normalize_public_branch_code((string) ($branchOption['code'] ?? 'kutisari'));
            $branchLabel = trim((string) ($branchOption['label'] ?? strtoupper($branchCode)));
            if ($branchLabel === '') {
                $branchLabel = strtoupper($branchCode);
            }
            $branchTargets = read_branch_discipleship_targets($branchCode);
            $branchTargetPeople = max(0, (int) ($branchTargets['dg_total_people'] ?? 50));
            $branchTargetMsk = max(0, (int) ($branchTargets['msk_completed'] ?? 50));
            $branchTargetDg1 = max(0, (int) ($branchTargets['dg1_people'] ?? 50));
            $branchTargetDg2 = max(0, (int) ($branchTargets['dg2_people'] ?? 50));
            $branchTargetDg3 = max(0, (int) ($branchTargets['dg3_people'] ?? 50));
            echo "        <tr><td>" . h($branchLabel) . "</td><td>" . h(number_format($branchTargetPeople, 0, ',', '.')) . "</td><td>" . h(number_format($branchTargetMsk, 0, ',', '.')) . "</td><td>" . h(number_format($branchTargetDg1, 0, ',', '.')) . "</td><td>" . h(number_format($branchTargetDg2, 0, ',', '.')) . "</td><td>" . h(number_format($branchTargetDg3, 0, ',', '.')) . "</td></tr>\n";
        }
        echo "      </tbody>\n";
        echo "    </table>\n";
        echo "  </div>\n";
        echo "</section>\n";
    } else {
        $targetDgTotalPeople = max(0, (int) ($discipleshipTargets['dg_total_people'] ?? 50));
        $targetMskCompleted = max(0, (int) ($discipleshipTargets['msk_completed'] ?? 50));
        $targetDg1People = max(0, (int) ($discipleshipTargets['dg1_people'] ?? 50));
        $targetDg2People = max(0, (int) ($discipleshipTargets['dg2_people'] ?? 50));
        $targetDg3People = max(0, (int) ($discipleshipTargets['dg3_people'] ?? 50));
        $activeBranchLabel = user_branch_label(current_user_branch());

        $targetCards = [
            [
                'class' => 'is-msk',
                'eyebrow' => 'MSK',
                'label' => 'Target Total Selesai MSK',
                'name' => 'target_msk_completed',
                'value' => $targetMskCompleted,
                'hint' => 'Jumlah peserta yang ditargetkan menuntaskan proses MSK.',
            ],
            [
                'class' => 'is-dg1',
                'eyebrow' => 'DG 1',
                'label' => 'Target Selesai DG 1',
                'name' => 'target_dg1_people',
                'value' => $targetDg1People,
                'hint' => 'Jumlah peserta yang ditargetkan menuntaskan DG 1.',
            ],
            [
                'class' => 'is-total',
                'eyebrow' => 'Kamp GAP',
                'label' => 'Target Peserta Kamp GAP',
                'name' => 'target_dg_total_people',
                'value' => $targetDgTotalPeople,
                'hint' => 'Jumlah peserta yang ditargetkan hadir di Kamp GAP.',
            ],
            [
                'class' => 'is-dg2',
                'eyebrow' => 'DG 2',
                'label' => 'Target Selesai DG 2',
                'name' => 'target_dg2_people',
                'value' => $targetDg2People,
                'hint' => 'Jumlah peserta yang ditargetkan menuntaskan DG 2.',
            ],
            [
                'class' => 'is-dg3',
                'eyebrow' => 'DG 3',
                'label' => 'Target Selesai DG 3',
                'name' => 'target_dg3_people',
                'value' => $targetDg3People,
                'hint' => 'Jumlah peserta yang ditargetkan menuntaskan DG 3.',
            ],
        ];

        echo "<section class=\"card settings-target-card\">\n";
        echo "  <div class=\"settings-target-hero\">\n";
        echo "    <div class=\"settings-target-copy\">\n";
        echo "      <span class=\"settings-target-kicker\">Target DG & MSK</span>\n";
        echo "      <h2>Cabang " . h($activeBranchLabel) . "</h2>\n";
        echo "      <p>Tetapkan sasaran DG dan MSK untuk cabang ini agar pemantauan pertumbuhan lebih jelas, terukur, dan konsisten.</p>\n";
        echo "    </div>\n";
        echo "    <div class=\"settings-target-meta\">\n";
        echo "      <span class=\"settings-target-badge is-branch\">Cabang " . h($activeBranchLabel) . "</span>\n";
        echo "      <span class=\"settings-target-badge\">" . h(CHURCH_NAME) . "</span>\n";
        echo "    </div>\n";
        echo "  </div>\n";
        echo "  <form method=\"post\" class=\"settings-target-form\">\n";
        echo "    <input type=\"hidden\" name=\"action\" value=\"save_discipleship_targets\">\n";
        echo "    <div class=\"settings-target-grid\">\n";
        foreach ($targetCards as $targetCard) {
            $cardClass = trim((string) ($targetCard['class'] ?? ''));
            $cardEyebrow = trim((string) ($targetCard['eyebrow'] ?? ''));
            $cardLabel = trim((string) ($targetCard['label'] ?? 'Target'));
            $cardName = trim((string) ($targetCard['name'] ?? ''));
            $cardValue = max(0, (int) ($targetCard['value'] ?? 0));
            $cardHint = trim((string) ($targetCard['hint'] ?? ''));
            echo "      <label class=\"settings-target-field " . h($cardClass) . "\">\n";
            echo "        <span class=\"settings-target-field-top\">\n";
            echo "          <span class=\"settings-target-field-eyebrow\">" . h($cardEyebrow) . "</span>\n";
            echo "          <span class=\"settings-target-field-preview\">" . h(number_format($cardValue, 0, ',', '.')) . "</span>\n";
            echo "        </span>\n";
            echo "        <span class=\"settings-target-field-title\">" . h($cardLabel) . "</span>\n";
            if ($cardHint !== '') {
                echo "        <span class=\"settings-target-field-hint\">" . h($cardHint) . "</span>\n";
            }
            echo "        <input type=\"number\" name=\"" . h($cardName) . "\" min=\"0\" max=\"1000000\" value=\"" . h((string) $cardValue) . "\" required>\n";
            echo "      </label>\n";
        }
        echo "    </div>\n";
        echo "    <div class=\"form-actions settings-target-actions\">\n";
        echo "      <button class=\"btn\" type=\"submit\">Simpan Target</button>\n";
        echo "    </div>\n";
        echo "  </form>\n";
        echo "</section>\n";
    }
    page_footer();
    legacy_exit();
}
