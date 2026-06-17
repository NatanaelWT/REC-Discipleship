<?php

function build_people_tree_group_history_views(array $model, array $peopleById, array $dgMeetingReports = []): array {
    $groupsById = [];
    foreach (($model['discipleship_groups'] ?? []) as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $groupId = trim((string) ($groupRow['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $groupsById[$groupId] = $groupRow;
    }

    $membershipsByGroupId = [];
    foreach (($model['group_memberships'] ?? []) as $membershipRow) {
        if (!is_array($membershipRow)) {
            continue;
        }
        $groupId = trim((string) ($membershipRow['group_id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $membershipsByGroupId[$groupId][] = $membershipRow;
    }

    $leadershipsByGroupId = [];
    foreach (($model['group_leaderships'] ?? []) as $leadershipRow) {
        if (!is_array($leadershipRow)) {
            continue;
        }
        $groupId = trim((string) ($leadershipRow['group_id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $leadershipsByGroupId[$groupId][] = $leadershipRow;
    }

    $multiplicationsBySourceGroupId = [];
    foreach (($model['group_multiplications'] ?? []) as $multiplicationRow) {
        if (!is_array($multiplicationRow)) {
            continue;
        }
        $sourceGroupId = trim((string) ($multiplicationRow['source_group_id'] ?? ''));
        if ($sourceGroupId === '') {
            continue;
        }
        $multiplicationsBySourceGroupId[$sourceGroupId][] = $multiplicationRow;
    }

    $reportsByGroupId = [];
    foreach ($dgMeetingReports as $reportRow) {
        if (!is_array($reportRow)) {
            continue;
        }
        $groupId = trim((string) ($reportRow['group_id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $reportsByGroupId[$groupId][] = $reportRow;
    }

    $personName = static function (string $personId) use ($peopleById): string {
        $label = trim((string) ($peopleById[$personId]['name'] ?? ''));
        return $label !== '' ? $label : '-';
    };
    $textLabel = static function (string $value, string $fallback = '-'): string {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }
        return ucwords(str_replace('_', ' ', $value));
    };
    $groupStatusLabel = static function (string $status): string {
        $status = strtolower(trim($status));
        if ($status === 'completed') {
            return 'Selesai';
        }
        if ($status === 'archived' || $status === 'closed' || $status === 'inactive') {
            return 'Tidak Aktif';
        }
        return 'Aktif';
    };
    $dateRangeLabel = static function (string $startDate, string $endDate): string {
        $startDate = normalize_ymd_date($startDate);
        $endDate = normalize_ymd_date($endDate);
        if ($startDate === '' && $endDate === '') {
            return '-';
        }
        $startLabel = $startDate !== '' ? format_indo_date($startDate) : '-';
        $endLabel = $endDate !== '' ? format_indo_date($endDate) : 'Sekarang';
        if ($startDate !== '' && $endDate !== '' && $startDate === $endDate) {
            return $startLabel;
        }
        return $startLabel . ' - ' . $endLabel;
    };
    $reasonLabel = static function (string $reason): string {
        $reason = trim($reason);
        if ($reason === 'continued_to_child_group') {
            return 'Kelompok selesai, lanjut ke kelompok berikutnya';
        }
        if ($reason === 'group_archived') {
            return 'Kelompok diarsipkan';
        }
        if ($reason === 'group_completed') {
            return 'Kelompok selesai';
        }
        if ($reason === 'stage_transition') {
            return 'Transisi tahap';
        }
        if ($reason === 'removed_from_group') {
            return 'Dikeluarkan dari kelompok';
        }
        if ($reason === 'left_group') {
            return 'Keluar dari kelompok';
        }
        return $reason !== '' ? ucwords(str_replace('_', ' ', $reason)) : '-';
    };

    $views = [];
    foreach ($groupsById as $groupId => $groupRow) {
        $groupStatus = strtolower(trim((string) ($groupRow['status'] ?? 'active')));
        $groupName = trim((string) ($groupRow['name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $groupStage = normalize_dg_progress_value((string) ($groupRow['current_stage'] ?? $groupRow['start_stage'] ?? ''));
        if ($groupStage === '') {
            $groupStage = 'DG 1';
        }
        $groupNotes = trim((string) ($groupRow['notes'] ?? ''));

        $leadershipRows = $leadershipsByGroupId[$groupId] ?? [];
        usort($leadershipRows, static function (array $a, array $b): int {
            $dateA = trim((string) ($a['start_date'] ?? $a['created_at'] ?? ''));
            $dateB = trim((string) ($b['start_date'] ?? $b['created_at'] ?? ''));
            if ($dateA !== $dateB) {
                return strcmp($dateA, $dateB);
            }
            return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
        });

        $membershipRows = $membershipsByGroupId[$groupId] ?? [];
        usort($membershipRows, static function (array $a, array $b): int {
            $dateA = trim((string) ($a['start_date'] ?? $a['created_at'] ?? ''));
            $dateB = trim((string) ($b['start_date'] ?? $b['created_at'] ?? ''));
            if ($dateA !== $dateB) {
                return strcmp($dateA, $dateB);
            }
            return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
        });

        $leaderNames = [];
        foreach ($leadershipRows as $leadershipRow) {
            $leaderPersonId = trim((string) ($leadershipRow['leader_person_id'] ?? ''));
            if ($leaderPersonId === '') {
                continue;
            }
            $label = $personName($leaderPersonId);
            if ($label !== '-' && !in_array($label, $leaderNames, true)) {
                $leaderNames[] = $label;
            }
        }

        $memberNames = [];
        foreach ($membershipRows as $membershipRow) {
            $memberPersonId = trim((string) ($membershipRow['person_id'] ?? ''));
            if ($memberPersonId === '') {
                continue;
            }
            $label = $personName($memberPersonId);
            if ($label !== '-' && !in_array($label, $memberNames, true)) {
                $memberNames[] = $label;
            }
        }

        $sourceGroupName = '-';
        $sourceGroupId = trim((string) ($groupRow['parent_group_id'] ?? ''));
        if ($sourceGroupId !== '' && isset($groupsById[$sourceGroupId])) {
            $sourceGroupName = trim((string) ($groupsById[$sourceGroupId]['name'] ?? 'Kelompok')) ?: 'Kelompok';
        }

        $continuedGroups = [];
        foreach (($multiplicationsBySourceGroupId[$groupId] ?? []) as $multiplicationRow) {
            $newGroupId = trim((string) ($multiplicationRow['new_group_id'] ?? ''));
            if ($newGroupId === '') {
                continue;
            }
            $newGroupRow = $groupsById[$newGroupId] ?? [];
            $newGroupName = trim((string) ($newGroupRow['name'] ?? 'Kelompok')) ?: 'Kelompok';
            $newGroupStage = normalize_dg_progress_value((string) ($newGroupRow['current_stage'] ?? $newGroupRow['start_stage'] ?? ''));
            if ($newGroupStage === '') {
                $newGroupStage = 'DG 1';
            }
            $continuedGroups[] = [
                'name' => $newGroupName,
                'stage' => $newGroupStage,
                'date' => $dateRangeLabel((string) ($multiplicationRow['start_date'] ?? ''), ''),
            ];
        }

        $reportRows = $reportsByGroupId[$groupId] ?? [];
        usort($reportRows, static function (array $a, array $b): int {
            return strcmp((string) ($b['meeting_date'] ?? ''), (string) ($a['meeting_date'] ?? ''));
        });
        $latestReport = $reportRows[0] ?? null;
        $latestReportDate = is_array($latestReport) ? normalize_ymd_date((string) ($latestReport['meeting_date'] ?? '')) : '';
        $latestReportTopic = is_array($latestReport) ? trim((string) ($latestReport['material_topic'] ?? '')) : '';

        ob_start();
        require resource_path('views/partials/people_tree_group_history_content.blade.php');

        $views[$groupId] = [
            'title' => 'Riwayat Kelompok ' . $groupName,
            'content' => (string) ob_get_clean(),
        ];
    }

    return $views;
}
