<?php

echo "<div class=\"journey-history-view tree-group-history-view\">";
echo "<div class=\"journey-history-summary\">";
echo "<div class=\"journey-history-summary-main\">";
echo "<div class=\"journey-history-summary-name\">" . h($groupName) . "</div>";
echo "<div class=\"journey-history-summary-sub\">" . h($groupStage) . " &bull; " . h($groupStatusLabel($groupStatus)) . "</div>";
echo "</div>";
echo "<div class=\"journey-history-summary-badges\">";
echo "<span class=\"journey-history-chip\">Jurnal " . h((string) count($reportRows)) . "</span>";
echo "<span class=\"journey-history-chip\">Laporan " . h($latestReportDate !== '' ? format_indo_date($latestReportDate) : '-') . "</span>";
echo "</div>";
echo "</div>";

if ($groupNotes !== '') {
    echo "<div class=\"journey-history-section-title\">Catatan Kelompok</div>";
    echo "<div class=\"journey-history-item-note tree-group-history-note\">" . h($groupNotes) . "</div>";
}

echo "<div class=\"journey-history-section-title\">Riwayat Pemimpin</div>";
if (count($leadershipRows) === 0) {
    echo "<div class=\"journey-history-empty\">Belum ada histori pemimpin yang tercatat untuk kelompok ini.</div>";
} else {
    echo "<div class=\"journey-history-timeline\">";
    foreach ($leadershipRows as $leadershipRow) {
        $leaderPersonId = trim((string) ($leadershipRow['leader_person_id'] ?? ''));
        $role = $textLabel((string) ($leadershipRow['role'] ?? 'leader'));
        $isActive = dgv2_is_current_period($leadershipRow);
        echo "<article class=\"journey-history-item\">";
        echo "<div class=\"journey-history-item-head\"><div class=\"journey-history-item-title\">" . h($personName($leaderPersonId)) . "</div><div class=\"journey-history-item-date\">" . h($dateRangeLabel((string) ($leadershipRow['start_date'] ?? ''), (string) ($leadershipRow['end_date'] ?? ''))) . "</div></div>";
        echo "<div class=\"journey-history-item-meta\"><span class=\"journey-history-chip\">" . h($role) . "</span>" . ($isActive ? "<span class=\"journey-history-chip is-active\">Aktif</span>" : '') . "</div>";
        $reasonChange = trim((string) ($leadershipRow['reason_change'] ?? ''));
        if ($reasonChange !== '') {
            echo "<div class=\"journey-history-item-note\">Catatan: " . h($reasonLabel($reasonChange)) . "</div>";
        }
        echo "</article>";
    }
    echo "</div>";
}

echo "<div class=\"journey-history-section-title\">Riwayat Anggota</div>";
if (count($membershipRows) === 0) {
    echo "<div class=\"journey-history-empty\">Belum ada histori anggota untuk kelompok ini.</div>";
} else {
    echo "<div class=\"journey-history-timeline\">";
    foreach ($membershipRows as $membershipRow) {
        $memberPersonId = trim((string) ($membershipRow['person_id'] ?? ''));
        $stage = normalize_dg_progress_value((string) ($membershipRow['stage'] ?? ''));
        $role = $textLabel((string) ($membershipRow['role'] ?? 'anggota'));
        $isActive = dgv2_is_current_period($membershipRow);
        echo "<article class=\"journey-history-item\">";
        echo "<div class=\"journey-history-item-head\"><div class=\"journey-history-item-title\">" . h($personName($memberPersonId)) . "</div><div class=\"journey-history-item-date\">" . h($dateRangeLabel((string) ($membershipRow['start_date'] ?? ''), (string) ($membershipRow['end_date'] ?? ''))) . "</div></div>";
        echo "<div class=\"journey-history-item-meta\">";
        if ($stage !== '') {
            echo "<span class=\"journey-history-chip\">" . h($stage) . "</span>";
        }
        echo "<span class=\"journey-history-chip\">" . h($role) . "</span>";
        if ($isActive) {
            echo "<span class=\"journey-history-chip is-active\">Masih aktif</span>";
        }
        echo "</div>";
        $reasonEnd = trim((string) ($membershipRow['reason_end'] ?? ''));
        if ($reasonEnd !== '') {
            echo "<div class=\"journey-history-item-note\">Catatan: " . h($reasonLabel($reasonEnd)) . "</div>";
        }
        echo "</article>";
    }
    echo "</div>";
}

echo "<div class=\"journey-history-section-title\">Riwayat Jurnal DG</div>";
if (count($reportRows) === 0) {
    echo "<div class=\"journey-history-empty\">Belum ada jurnal DG yang tersimpan untuk kelompok ini.</div>";
} else {
    echo "<div class=\"journey-history-item-note tree-group-history-note\">";
    echo "Total " . h((string) count($reportRows)) . " jurnal";
    if ($latestReportTopic !== '') {
        echo " &bull; Terakhir: " . h($latestReportTopic);
    }
    echo "</div>";
}

echo "</div>";
