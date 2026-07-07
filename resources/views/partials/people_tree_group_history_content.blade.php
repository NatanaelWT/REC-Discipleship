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
$normalizeReportPersonNames = static function (mixed $value): array {
    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        $value = explode(',', $value);
    }
    if (! is_array($value)) {
        return [];
    }

    $names = [];
    foreach ($value as $row) {
        if (is_array($row)) {
            $name = trim((string) ($row['name'] ?? $row['full_name'] ?? $row['label'] ?? ''));
        } else {
            $name = trim((string) $row);
        }
        if ($name !== '' && ! in_array($name, $names, true)) {
            $names[] = $name;
        }
    }

    return $names;
};
$reportPersonNames = static function (mixed $personIds, mixed $fallbackNames = []) use ($personName, $normalizeReportPersonNames): string {
    $names = [];
    if (is_array($personIds)) {
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
    }
    foreach ($normalizeReportPersonNames($fallbackNames) as $name) {
        if (! in_array($name, $names, true)) {
            $names[] = $name;
        }
    }

    return count($names) > 0 ? implode(', ', $names) : '-';
};
$isTruthy = static function (mixed $value): bool {
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'ya'], true);
};
$stagePillClass = static function (string $stage): string {
    return match (normalize_dg_progress_value($stage)) {
        'DG 1' => 'is-dg1',
        'DG 2' => 'is-dg2',
        'DG 3' => 'is-dg3',
        default => 'is-neutral',
    };
};
$rosterMetaLabel = static function (array $parts): string {
    $filtered = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $filtered[] = $part;
        }
    }

    return implode(' - ', $filtered);
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

echo "<section class=\"tree-group-history-roster\">";
echo "<div class=\"tree-group-history-roster-head\"><div class=\"journey-history-section-title\">Pemimpin &amp; Anggota</div><span>" . h((string) count($leadershipRows)) . " pemimpin - " . h((string) count($membershipRows)) . " anggota</span></div>";
if (count($leadershipRows) === 0 && count($membershipRows) === 0) {
    echo "<div class=\"journey-history-empty\">Belum ada histori pemimpin atau anggota.</div>";
} else {
    echo "<div class=\"tree-group-history-roster-list\">";
    foreach ($leadershipRows as $leadershipRow) {
        $leaderPersonId = trim((string) ($leadershipRow['leader_person_id'] ?? ''));
        $role = $textLabel((string) ($leadershipRow['role'] ?? 'leader'));
        $isActive = dgv2_is_current_period($leadershipRow);
        echo "<article class=\"tree-group-history-roster-pill is-leader\">";
        echo "<span class=\"tree-group-history-roster-kind\">Pemimpin</span>";
        echo "<strong>" . h($personName($leaderPersonId)) . "</strong>";
        echo "<span class=\"tree-group-history-roster-meta\">" . h($rosterMetaLabel([$role, $isActive ? 'Aktif' : '', $compactDateLabel($leadershipRow)])) . "</span>";
        $reasonChange = trim((string) ($leadershipRow['reason_change'] ?? ''));
        if ($reasonChange !== '') {
            echo "<span class=\"tree-group-history-roster-note\">" . h($reasonLabel($reasonChange)) . "</span>";
        }
        echo "</article>";
    }
    foreach ($membershipRows as $membershipRow) {
        $memberPersonId = trim((string) ($membershipRow['person_id'] ?? ''));
        $stage = normalize_dg_progress_value((string) ($membershipRow['stage'] ?? ''));
        $role = $textLabel((string) ($membershipRow['role'] ?? 'anggota'));
        $isActive = dgv2_is_current_period($membershipRow);
        echo "<article class=\"tree-group-history-roster-pill is-member\">";
        echo "<span class=\"tree-group-history-roster-kind\">Anggota</span>";
        echo "<strong>" . h($personName($memberPersonId)) . "</strong>";
        echo "<span class=\"tree-group-history-roster-meta\">" . h($rosterMetaLabel([$stage, $role, $isActive ? 'Masih aktif' : '', $compactDateLabel($membershipRow)])) . "</span>";
        $reasonEnd = trim((string) ($membershipRow['reason_end'] ?? ''));
        if ($reasonEnd !== '') {
            echo "<span class=\"tree-group-history-roster-note\">" . h($reasonLabel($reasonEnd)) . "</span>";
        }
        echo "</article>";
    }
    echo "</div>";
}
echo "</section>";

echo "<div class=\"journey-history-section-title\">Riwayat Jurnal Temu DG</div>";
if (count($reportRows) === 0) {
    echo "<div class=\"journey-history-empty\">Belum ada jurnal DG yang tersimpan untuk kelompok ini.</div>";
} else {
    echo "<div class=\"table-wrap dg-recap-group-report-table-wrap tree-group-journal-table-wrap\"><table class=\"table dg-recap-table dg-recap-group-report-table tree-group-journal-table\"><thead><tr><th>Tanggal</th><th>Materi</th><th>Anggota Tidak Hadir</th><th>Kualitas Pemimpin</th><th>Sharing</th><th>Pembagi Meditasi</th><th>Catatan</th><th>Foto</th></tr></thead><tbody>";
    foreach ($reportRows as $reportRow) {
        $meetingDate = normalize_ymd_date((string) ($reportRow['meeting_date'] ?? ''));
        $meetingDateLabel = $meetingDate !== '' ? format_indo_date($meetingDate) : '-';
        $materialTopic = trim((string) ($reportRow['material_topic'] ?? ''));
        $progress = normalize_dg_progress_value((string) ($reportRow['group_progress'] ?? ''));
        $absentNames = $reportPersonNames($reportRow['absent_member_ids'] ?? [], $reportRow['absent_member_names'] ?? []);
        $meditationSharerNames = $reportPersonNames($reportRow['meditation_sharer_ids'] ?? [], $reportRow['meditation_sharer_names'] ?? []);
        $absenceReason = trim((string) ($reportRow['absence_reason'] ?? ''));
        $additionalNotes = trim((string) ($reportRow['additional_notes'] ?? ''));
        $photos = is_array($reportRow['meeting_photos'] ?? null) ? $reportRow['meeting_photos'] : [];
        $createdAt = trim((string) ($reportRow['created_at'] ?? ''));
        $meditationMinTimes = max(0, (int) ($reportRow['meditation_min_times'] ?? 0));
        $sharingScore = is_numeric($reportRow['sharing_openness'] ?? null) ? (int) $reportRow['sharing_openness'] : 0;
        $sharingLabel = $sharingScore >= 1 && $sharingScore <= 10 ? ((string) $sharingScore) . ' / 10' : '-';
        $qualityTags = [];
        if ($isTruthy($reportRow['quality_prepare'] ?? 'false')) {
            $qualityTags[] = 'Persiapan Materi';
        }
        if ($isTruthy($reportRow['quality_pray'] ?? 'false')) {
            $qualityTags[] = 'Mendoakan Anggota';
        }
        if ($isTruthy($reportRow['quality_share_meditation'] ?? 'false')) {
            $qualityTags[] = 'Share Meditasi';
        }
        if ($isTruthy($reportRow['quality_relational'] ?? 'false')) {
            $qualityTags[] = 'Komunikasi Relasional';
        }

        echo "<tr>";
        echo "<td class=\"dg-recap-text\"><div class=\"dg-recap-main-cell\"><div class=\"dg-recap-main-title\">" . h($meetingDateLabel) . "</div>";
        if ($progress !== '') {
            echo "<div><span class=\"dg-recap-stage-pill " . h($stagePillClass($progress)) . "\">" . h($progress) . "</span></div>";
        }
        if ($createdAt !== '') {
            echo "<div class=\"dg-recap-subtext\">Dikirim " . h(format_datetime_id($createdAt)) . "</div>";
        }
        echo "</div></td>";
        echo "<td class=\"dg-recap-text\">" . h($materialTopic !== '' ? $materialTopic : '-') . "</td>";
        echo "<td class=\"dg-recap-text\">" . h($absentNames !== '-' ? $absentNames : 'Tidak ada');
        if ($absenceReason !== '') {
            echo "<div class=\"dg-recap-subtext\">Alasan: " . h($absenceReason) . "</div>";
        }
        echo "</td>";
        echo "<td>";
        if (count($qualityTags) === 0) {
            echo '-';
        } else {
            echo "<div class=\"dg-recap-chip-list\">";
            foreach ($qualityTags as $tag) {
                echo "<span class=\"chip\">" . h($tag) . "</span>";
            }
            echo "</div>";
        }
        echo "</td>";
        echo "<td><div class=\"dg-recap-number-chip\">" . h($sharingLabel) . "</div></td>";
        echo "<td class=\"dg-recap-text\">" . h($meditationSharerNames) . "<div class=\"dg-recap-subtext\">Min. renungan " . h((string) $meditationMinTimes) . "x</div></td>";
        echo "<td class=\"dg-recap-text\">" . h($additionalNotes !== '' ? $additionalNotes : '-') . "</td>";
        echo "<td class=\"dg-recap-text\">";
        if (count($photos) === 0) {
            echo '-';
        } else {
            echo h((string) count($photos) . ' foto');
            echo "<div class=\"dg-recap-photo-links tree-group-journal-photo-links\">";
            foreach (array_values(array_slice($photos, 0, 3)) as $photoIndex => $photo) {
                if (! is_array($photo)) {
                    continue;
                }
                $path = trim((string) ($photo['path'] ?? ''));
                $name = trim((string) ($photo['name'] ?? '')) ?: 'Foto jurnal';
                $url = function_exists('secure_upload_url') ? secure_upload_url($path, false, $name) : '';
                if ($url !== '') {
                    echo "<a class=\"note-link\" href=\"" . h($url) . "\" target=\"_blank\" rel=\"noopener\" title=\"" . h($name) . "\">Lihat foto " . h((string) ($photoIndex + 1)) . "</a>";
                }
            }
            echo "</div>";
            if (count($photos) > 3) {
                echo "<div class=\"dg-recap-subtext\">+" . h((string) (count($photos) - 3)) . " foto lainnya</div>";
            }
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
}

echo "</div>";
