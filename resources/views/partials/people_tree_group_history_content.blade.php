<?php

$compactDateLabel = static function (array $row): string {
    $startDate = normalize_ymd_date((string) ($row['start_date'] ?? ''));
    $endDate = normalize_ymd_date((string) ($row['end_date'] ?? ''));
    if ($startDate === '' && $endDate === '') {
        return '-';
    }

    $startLabel = $startDate !== '' ? format_short_indo_date($startDate) : '-';
    $endLabel = $endDate !== '' ? format_short_indo_date($endDate) : 'Sekarang';

    return $startDate !== '' && $endDate !== '' && $startDate === $endDate
        ? $startLabel
        : $startLabel . ' - ' . $endLabel;
};
$reportPersonNames = static function (array $personIds) use ($personName): string {
    $names = [];
    foreach ($personIds as $personId) {
        $personId = trim((string) $personId);
        if ($personId === '') {
            continue;
        }
        $name = $personName($personId);
        if ($name !== '-' && ! in_array($name, $names, true)) {
            $names[] = $name;
        }
    }

    return count($names) > 0 ? implode(', ', $names) : '-';
};
$yesNoLabel = static function (mixed $value): string {
    $value = strtolower(trim((string) $value));

    return in_array($value, ['1', 'true', 'yes', 'ya'], true) ? 'Ya' : 'Tidak';
};

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

echo "<div class=\"tree-group-history-people-grid\">";
echo "<section class=\"tree-group-history-compact-section\">";
echo "<div class=\"journey-history-section-title\">Pemimpin</div>";
if (count($leadershipRows) > 0) {
    echo "<div class=\"tree-group-history-compact-list\">";
    foreach ($leadershipRows as $leadershipRow) {
        $leaderPersonId = trim((string) ($leadershipRow['leader_person_id'] ?? ''));
        $role = $textLabel((string) ($leadershipRow['role'] ?? 'leader'));
        $isActive = dgv2_is_current_period($leadershipRow);
        echo "<article class=\"tree-group-history-person-pill\">";
        echo "<div class=\"tree-group-history-person-main\"><strong>" . h($personName($leaderPersonId)) . "</strong><span>" . h($compactDateLabel($leadershipRow)) . "</span></div>";
        echo "<div class=\"journey-history-item-meta\"><span class=\"journey-history-chip\">" . h($role) . "</span>" . ($isActive ? "<span class=\"journey-history-chip is-active\">Aktif</span>" : '') . "</div>";
        $reasonChange = trim((string) ($leadershipRow['reason_change'] ?? ''));
        if ($reasonChange !== '') {
            echo "<div class=\"tree-group-history-person-note\">" . h($reasonLabel($reasonChange)) . "</div>";
        }
        echo "</article>";
    }
    echo "</div>";
} else {
    echo "<div class=\"journey-history-empty\">Belum ada histori pemimpin.</div>";
}
echo "</section>";

echo "<section class=\"tree-group-history-compact-section\">";
echo "<div class=\"journey-history-section-title\">Anggota</div>";
if (count($membershipRows) > 0) {
    echo "<div class=\"tree-group-history-compact-list\">";
    foreach ($membershipRows as $membershipRow) {
        $memberPersonId = trim((string) ($membershipRow['person_id'] ?? ''));
        $stage = normalize_dg_progress_value((string) ($membershipRow['stage'] ?? ''));
        $role = $textLabel((string) ($membershipRow['role'] ?? 'anggota'));
        $isActive = dgv2_is_current_period($membershipRow);
        echo "<article class=\"tree-group-history-person-pill\">";
        echo "<div class=\"tree-group-history-person-main\"><strong>" . h($personName($memberPersonId)) . "</strong><span>" . h($compactDateLabel($membershipRow)) . "</span></div>";
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
            echo "<div class=\"tree-group-history-person-note\">" . h($reasonLabel($reasonEnd)) . "</div>";
        }
        echo "</article>";
    }
    echo "</div>";
} else {
    echo "<div class=\"journey-history-empty\">Belum ada histori anggota.</div>";
}
echo "</section>";
echo "</div>";

echo "<div class=\"journey-history-section-title\">Riwayat Jurnal Temu DG</div>";
if (count($reportRows) === 0) {
    echo "<div class=\"journey-history-empty\">Belum ada jurnal DG yang tersimpan untuk kelompok ini.</div>";
} else {
    echo "<div class=\"tree-group-journal-list\">";
    foreach ($reportRows as $reportRow) {
        $meetingDate = normalize_ymd_date((string) ($reportRow['meeting_date'] ?? ''));
        $meetingDateLabel = $meetingDate !== '' ? format_indo_date($meetingDate) : '-';
        $materialTopic = trim((string) ($reportRow['material_topic'] ?? ''));
        $progress = normalize_dg_progress_value((string) ($reportRow['group_progress'] ?? ''));
        $absentNames = $reportPersonNames(is_array($reportRow['absent_member_ids'] ?? null) ? $reportRow['absent_member_ids'] : []);
        $meditationSharerNames = $reportPersonNames(is_array($reportRow['meditation_sharer_ids'] ?? null) ? $reportRow['meditation_sharer_ids'] : []);
        $absenceReason = trim((string) ($reportRow['absence_reason'] ?? ''));
        $additionalNotes = trim((string) ($reportRow['additional_notes'] ?? ''));
        $photos = is_array($reportRow['meeting_photos'] ?? null) ? $reportRow['meeting_photos'] : [];
        $createdAt = trim((string) ($reportRow['created_at'] ?? ''));

        echo "<article class=\"journey-history-item tree-group-journal-item\">";
        echo "<div class=\"journey-history-item-head\">";
        echo "<div class=\"journey-history-item-title\">" . h($materialTopic !== '' ? $materialTopic : 'Jurnal Temu DG') . "</div>";
        echo "<div class=\"journey-history-item-date\">" . h($meetingDateLabel) . "</div>";
        echo "</div>";
        echo "<div class=\"journey-history-item-meta\">";
        if ($progress !== '') {
            echo "<span class=\"journey-history-chip\">" . h($progress) . "</span>";
        }
        echo "<span class=\"journey-history-chip\">Renungan min. " . h((string) max(0, (int) ($reportRow['meditation_min_times'] ?? 0))) . "x</span>";
        $sharingScore = (int) ($reportRow['sharing_openness'] ?? 0);
        if ($sharingScore > 0) {
            echo "<span class=\"journey-history-chip\">Sharing " . h((string) $sharingScore) . "/10</span>";
        }
        if ($createdAt !== '') {
            echo "<span class=\"journey-history-chip\">Dikirim " . h(format_datetime_id($createdAt)) . "</span>";
        }
        echo "</div>";

        echo "<div class=\"tree-group-journal-details\">";
        echo "<div><span>Yang absen</span><strong>" . h($absentNames !== '-' ? $absentNames : 'Tidak ada') . "</strong></div>";
        echo "<div><span>Alasan absen</span><strong>" . h($absenceReason !== '' ? $absenceReason : '-') . "</strong></div>";
        echo "<div><span>Sharing renungan</span><strong>" . h($meditationSharerNames) . "</strong></div>";
        echo "<div><span>Sumber laporan</span><strong>" . h($textLabel((string) ($reportRow['source'] ?? 'public_form'))) . "</strong></div>";
        echo "</div>";

        echo "<div class=\"tree-group-journal-quality\">";
        echo "<span>Materi siap: " . h($yesNoLabel($reportRow['quality_prepare'] ?? 'false')) . "</span>";
        echo "<span>Mendoakan anggota: " . h($yesNoLabel($reportRow['quality_pray'] ?? 'false')) . "</span>";
        echo "<span>Renungan dibagikan: " . h($yesNoLabel($reportRow['quality_share_meditation'] ?? 'false')) . "</span>";
        echo "<span>Kontak relasional: " . h($yesNoLabel($reportRow['quality_relational'] ?? 'false')) . "</span>";
        echo "</div>";

        if ($additionalNotes !== '') {
            echo "<div class=\"journey-history-item-note\"><strong>Catatan tambahan:</strong> " . h($additionalNotes) . "</div>";
        }
        if (count($photos) > 0) {
            echo "<div class=\"tree-group-journal-photos\"><span>Foto:</span>";
            foreach ($photos as $photo) {
                if (! is_array($photo)) {
                    continue;
                }
                $path = trim((string) ($photo['path'] ?? ''));
                $name = trim((string) ($photo['name'] ?? '')) ?: 'Foto jurnal';
                $url = function_exists('secure_upload_url') ? secure_upload_url($path, false, $name) : '';
                if ($url !== '') {
                    echo "<a href=\"" . h($url) . "\" target=\"_blank\" rel=\"noopener\">" . h($name) . "</a>";
                } else {
                    echo "<span>" . h($name) . "</span>";
                }
            }
            echo "</div>";
        }
        echo "</article>";
    }
    echo "</div>";
}

echo "</div>";
