<?php

if (in_array($page, ['discipleship_dashboard', 'groups_list', 'people_list'], true)) {
    $discipleshipPageTitleMap = [
        'discipleship_dashboard' => 'Dashboard Pemuridan',
        'groups_list' => 'Kelompok Pemuridan',
        'people_list' => 'Daftar Orang',
    ];
    $discipleshipBodyClass = '';
    if ($page === 'discipleship_dashboard') {
        $discipleshipBodyClass = 'page-discipleship-dashboard';
    } elseif ($page === 'groups_list') {
        $discipleshipBodyClass = 'page-discipleship-groups-list';
    } elseif ($page === 'people_list') {
        $discipleshipBodyClass = 'page-discipleship-people-list';
    }
    $showDiscipleshipTitle = !in_array($page, ['discipleship_dashboard', 'groups_list', 'people_list'], true);
    page_header((string) ($discipleshipPageTitleMap[$page] ?? 'Dashboard Pemuridan'), $settings, $page, $showDiscipleshipTitle, $discipleshipBodyClass);

    $showDashboardStats = $page === 'discipleship_dashboard';
    $showGroupsTable = $page === 'groups_list';
    $showPeopleTable = $page === 'people_list';
    $centralReadOnly = is_effective_central_discipleship_readonly();

    if ($showDashboardStats) {
        if (isset($_GET['msk_session_saved'])) {
            echo "<div class=\"alert success\">Progress sesi MSK berhasil diperbarui.</div>\n";
        }
        if (isset($_GET['converted'])) {
            echo "<div class=\"alert success\">Peserta luar jemaat yang menyelesaikan 12 sesi otomatis ditambahkan ke data jemaat.</div>\n";
        }
        $dashboardError = trim((string) ($_GET['error'] ?? ''));
        if ($dashboardError === 'invalid_msk_participant') {
            echo "<div class=\"alert danger\">Data peserta kelas MSK tidak ditemukan.</div>\n";
        }
        if (!$centralReadOnly) {
            render_pemuridan_import_feedback();
        }
    }

    $renderDashboardMskSessionForm = function (array $participant, string $mskMonthLabel): string {
        $participantId = trim((string) ($participant['id'] ?? ''));
        $participantName = trim((string) ($participant['full_name'] ?? ''));
        if ($participantName === '') {
            $participantName = 'Tanpa Nama';
        }
        $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
        $sessionMap = [];
        foreach ($sessionNumbers as $sessionNumber) {
            $sessionMap[(string) $sessionNumber] = true;
        }
        $progressLabel = (string) count($sessionNumbers) . '/12 sesi';
        if (trim($mskMonthLabel) === '') {
            $mskMonthLabel = '-';
        }

        ob_start();
        echo "<form method=\"post\" class=\"form-grid\">\n";
        echo "  <div class=\"panel-note\" style=\"grid-column:1/-1;\">Peserta: <strong>" . h($participantName) . "</strong><br>Batch Mulai MSK: " . h($mskMonthLabel) . "<br>Progress Saat Ini: " . h($progressLabel) . "</div>\n";
        echo "  <fieldset class=\"dg-checklist msk-session-checklist\" style=\"grid-column:1/-1;\">\n";
        echo "    <legend>Edit Checklist 12 Sesi MSK</legend>\n";
        echo "    <div class=\"msk-session-grid\">\n";
        for ($session = 1; $session <= 12; $session++) {
            $checked = isset($sessionMap[(string) $session]) ? 'checked' : '';
            echo "    <label class=\"check-label\"><input type=\"checkbox\" name=\"session_numbers[]\" value=\"" . h((string) $session) . "\" " . $checked . ">Sesi " . h((string) $session) . "</label>\n";
        }
        echo "    </div>\n";
        echo "  </fieldset>\n";
        echo "  <input type=\"hidden\" name=\"action\" value=\"save_msk_sessions\">\n";
        echo "  <input type=\"hidden\" name=\"id\" value=\"" . h($participantId) . "\">\n";
        echo "  <input type=\"hidden\" name=\"return_page\" value=\"discipleship_dashboard\">\n";
        echo "  <div class=\"modal-actions\" style=\"grid-column:1/-1;\">\n";
        echo "    <button class=\"btn\" type=\"submit\">Simpan Sesi</button>\n";
        echo "    <button class=\"btn ghost\" type=\"button\" data-msk-edit-close>Batal</button>\n";
        echo "  </div>\n";
        echo "</form>\n";

        return (string) ob_get_clean();
    };

    $peopleById = index_by_id($people);
    $totalPeople = count($people);
    $totalLeaders = 0;
    $totalAnggota = 0;
    foreach ($people as $p) {
        $role = (string) ($p['role'] ?? '');
        if ($role === 'Anggota') {
            $totalAnggota++;
        } elseif ($role !== '') {
            $totalLeaders++;
        }
    }

    $kelompokCount = count($groups);
    $totalCompletedMsk = 0;
    $totalMskParticipants = 0;
    $totalIncompleteMsk = 0;
    $incompleteMskRows = [];
    $incompleteMskEditTemplates = [];
    $seenIncompleteMskKeys = [];
    foreach ($mskClasses as $participantRow) {
        if (!is_array($participantRow)) {
            continue;
        }
        if (normalize_msk_participant_status((string) ($participantRow['status'] ?? 'active')) !== 'active') {
            continue;
        }
        $participantId = trim((string) ($participantRow['id'] ?? ''));
        $participantName = trim((string) ($participantRow['full_name'] ?? ''));
        if ($participantId === '' && $participantName === '') {
            continue;
        }
        $totalMskParticipants++;
        if (msk_is_complete($participantRow)) {
            $totalCompletedMsk++;
        } else {
            $totalIncompleteMsk++;
            $sessionCount = count(normalize_msk_session_numbers($participantRow['session_numbers'] ?? []));
            if ($sessionCount > 12) {
                $sessionCount = 12;
            }
            if ($sessionCount < 0) {
                $sessionCount = 0;
            }
            $participantKey = $participantId !== '' ? ('id:' . $participantId) : ('name:' . strtolower($participantName));
            if (!isset($seenIncompleteMskKeys[$participantKey])) {
                $seenIncompleteMskKeys[$participantKey] = true;
                $phoneLabel = trim((string) ($participantRow['whatsapp'] ?? ''));
                if ($phoneLabel === '') {
                    $phoneLabel = '-';
                }
                $branchLabel = trim((string) ($participantRow['branch_label'] ?? ''));
                $mskMonth = trim((string) ($participantRow['msk_month'] ?? ''));
                $mskMonthSort = '';
                if (preg_match('/^\d{4}-\d{2}$/', $mskMonth) === 1) {
                    $mskYear = (int) substr($mskMonth, 0, 4);
                    $mskMonthNumber = (int) substr($mskMonth, 5, 2);
                    if ($mskYear >= 2000 && $mskYear <= 2100 && $mskMonthNumber >= 1 && $mskMonthNumber <= 12) {
                        $mskMonthSort = sprintf('%04d-%02d', $mskYear, $mskMonthNumber);
                    }
                }
                $mskMonthLabel = $mskMonthSort !== '' ? format_indo_month($mskMonthSort) : '-';
                $incompleteMskRows[] = [
                    'participant_id' => $participantId,
                    'name' => $participantName !== '' ? $participantName : '-',
                    'session_count' => $sessionCount,
                    'progress_label' => (string) $sessionCount . '/12 sesi',
                    'phone' => $phoneLabel,
                    'branch_label' => $branchLabel,
                    'msk_month' => $mskMonthSort,
                    'msk_month_label' => $mskMonthLabel,
                ];
                if ($showDashboardStats && !$centralReadOnly && $participantId !== '') {
                    $incompleteMskEditTemplates[$participantId] = [
                        'title' => 'Edit Sesi MSK: ' . ($participantName !== '' ? $participantName : 'Tanpa Nama'),
                        'content' => $renderDashboardMskSessionForm($participantRow, $mskMonthLabel),
                    ];
                }
            }
        }
    }
    $autoOpenIncompleteMskEditId = '';
    if ($showDashboardStats && !$centralReadOnly) {
        $requestedIncompleteMskEditId = trim((string) ($_GET['edit_msk_sessions'] ?? ''));
        if ($requestedIncompleteMskEditId !== '' && isset($incompleteMskEditTemplates[$requestedIncompleteMskEditId])) {
            $autoOpenIncompleteMskEditId = $requestedIncompleteMskEditId;
        }
    }
    $dgCurrentGroupProgressById = [];
    $dgRecordedGroupKeys = [];
    $dgActiveLeaderKeys = [];
    foreach ($groups as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $groupId = trim((string) ($groupRow['id'] ?? ''));
        $groupName = trim((string) ($groupRow['name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $groupLeaderId = trim((string) ($groupRow['leader_id'] ?? ''));
        $groupLeaderName = person_label($peopleById, $groupLeaderId, '-');
        $groupLeaderKey = $groupLeaderId !== '' ? ('id:' . $groupLeaderId) : ('name:' . strtolower($groupLeaderName));
        if ($groupLeaderId !== '' || $groupLeaderName !== '-') {
            $dgActiveLeaderKeys[$groupLeaderKey] = true;
        }
        $groupKey = $groupId !== '' ? ('id:' . $groupId) : ('name:' . strtolower($groupName . '|' . $groupLeaderKey));
        $dgRecordedGroupKeys[$groupKey] = true;

        $groupProgress = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        if ($groupProgress === '') {
            $groupProgress = 'DG 1';
        }
        if ($groupId !== '') {
            $dgCurrentGroupProgressById[$groupId] = $groupProgress;
        }
    }
    $dgTotalReports = 0;
    $dgTotalAbsentMembers = 0;
    foreach ($dgMeetingReports as $reportRow) {
        if (!is_array($reportRow)) {
            continue;
        }
        $groupId = trim((string) ($reportRow['group_id'] ?? ''));
        $groupProgress = normalize_dg_progress_value((string) ($reportRow['group_progress'] ?? ''));
        if ($groupProgress === '') {
            $groupProgress = 'DG 1';
        }
        $currentGroupProgress = $groupId !== '' ? trim((string) ($dgCurrentGroupProgressById[$groupId] ?? '')) : '';
        if ($groupId !== '' && $currentGroupProgress !== '' && $groupProgress !== $currentGroupProgress) {
            continue;
        }

        $dgTotalReports++;
        $absentMemberNames = $reportRow['absent_member_names'] ?? [];
        if (!is_array($absentMemberNames)) {
            $absentMemberNames = [];
        }
        $normalizedAbsentNames = [];
        foreach ($absentMemberNames as $absentNameRaw) {
            $absentName = trim((string) $absentNameRaw);
            if ($absentName === '' || in_array($absentName, $normalizedAbsentNames, true)) {
                continue;
            }
            $normalizedAbsentNames[] = $absentName;
        }
        $absentCount = count($normalizedAbsentNames);
        if ($absentCount === 0) {
            $absentMemberIds = $reportRow['absent_member_ids'] ?? [];
            if (is_array($absentMemberIds)) {
                $normalizedAbsentIds = [];
                foreach ($absentMemberIds as $absentIdRaw) {
                    $absentId = trim((string) $absentIdRaw);
                    if ($absentId === '') {
                        continue;
                    }
                    $normalizedAbsentIds[$absentId] = true;
                }
                $absentCount = count($normalizedAbsentIds);
            }
        }
        $dgTotalAbsentMembers += $absentCount;
    }
    $dgTotalLeaders = count($dgActiveLeaderKeys);
    $dgTotalGroups = count($dgRecordedGroupKeys);
    $dgProgressChartRows = [
        ['label' => 'DG 1', 'value' => 0, 'color' => discipleship_stage_color('DG 1')],
        ['label' => 'DG 2', 'value' => 0, 'color' => discipleship_stage_color('DG 2')],
        ['label' => 'DG 3', 'value' => 0, 'color' => discipleship_stage_color('DG 3')],
    ];
    foreach ($groups as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $progress = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        if ($progress === 'DG 1') {
            $dgProgressChartRows[0]['value']++;
        } elseif ($progress === 'DG 2') {
            $dgProgressChartRows[1]['value']++;
        } elseif ($progress === 'DG 3') {
            $dgProgressChartRows[2]['value']++;
        }
    }
    $personHasChildrenMap = [];
    foreach ($people as $personRow) {
        if (!is_array($personRow)) {
            continue;
        }
        $parentIds = get_parent_ids($personRow);
        foreach ($parentIds as $parentIdRaw) {
            $parentId = trim((string) $parentIdRaw);
            if ($parentId !== '') {
                $personHasChildrenMap[$parentId] = true;
            }
        }
    }
    // Keep DG target counting consistent with "Daftar Orang" progress column:
    // progress is derived from group member mapping per person.
    $peopleProgressMapForTarget = [];
    foreach ($groups as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $progressLabel = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        if ($progressLabel === '') {
            $progressLabel = '-';
        }
        $memberIds = $groupRow['member_ids'] ?? [];
        if (!is_array($memberIds)) {
            continue;
        }
        foreach ($memberIds as $memberIdRaw) {
            $memberId = trim((string) $memberIdRaw);
            if ($memberId === '' || !isset($peopleById[$memberId])) {
                continue;
            }
            if (!isset($peopleProgressMapForTarget[$memberId])) {
                $peopleProgressMapForTarget[$memberId] = [];
            }
            if (!in_array($progressLabel, $peopleProgressMapForTarget[$memberId], true)) {
                $peopleProgressMapForTarget[$memberId][] = $progressLabel;
            }
        }
    }
    $dgPeopleCountByStage = [
        'DG 1' => 0,
        'DG 2' => 0,
        'DG 3' => 0,
    ];
    foreach ($people as $personRow) {
        if (!is_array($personRow)) {
            continue;
        }
        $personId = trim((string) ($personRow['id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $progressValues = $peopleProgressMapForTarget[$personId] ?? [];
        if (!is_array($progressValues)) {
            $progressValues = [];
        }
        foreach ($progressValues as $progressValueRaw) {
            $progressValue = trim((string) $progressValueRaw);
            if ($progressValue === 'DG 1' || $progressValue === 'DG 2' || $progressValue === 'DG 3') {
                $dgPeopleCountByStage[$progressValue]++;
            }
        }
    }
    $dgPeopleProgressRows = [
        ['label' => 'DG 1', 'value' => (int) ($dgPeopleCountByStage['DG 1'] ?? 0), 'color' => discipleship_stage_color('DG 1')],
        ['label' => 'DG 2', 'value' => (int) ($dgPeopleCountByStage['DG 2'] ?? 0), 'color' => discipleship_stage_color('DG 2')],
        ['label' => 'DG 3', 'value' => (int) ($dgPeopleCountByStage['DG 3'] ?? 0), 'color' => discipleship_stage_color('DG 3')],
    ];
    // Keep dashboard "Peserta Aktif" aligned with the stage-specific "Sedang DG" filters in Daftar Anggota DG.
    $dashboardDgStageRank = static function (string $stage): int {
        $stage = normalize_dg_progress_value($stage);
        if ($stage === 'DG 3') {
            return 3;
        }
        if ($stage === 'DG 2') {
            return 2;
        }
        if ($stage === 'DG 1') {
            return 1;
        }
        return 0;
    };
    $dashboardPeopleLastProgressMap = [];
    foreach (($discipleshipV2Model['group_memberships'] ?? []) as $membershipRow) {
        if (!is_array($membershipRow)) {
            continue;
        }
        $personId = trim((string) ($membershipRow['person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $stage = normalize_dg_progress_value((string) ($membershipRow['stage'] ?? ''));
        if ($stage === '') {
            continue;
        }
        $sortDate = trim((string) ($membershipRow['end_date'] ?? ''));
        if ($sortDate === '') {
            $sortDate = trim((string) ($membershipRow['start_date'] ?? ''));
        }
        if ($sortDate === '') {
            $sortDate = trim((string) ($membershipRow['updated_at'] ?? $membershipRow['created_at'] ?? ''));
        }
        $existing = $dashboardPeopleLastProgressMap[$personId] ?? null;
        if (!is_array($existing)) {
            $dashboardPeopleLastProgressMap[$personId] = [
                'stage' => $stage,
                'sort_date' => $sortDate,
                'stage_rank' => $dashboardDgStageRank($stage),
            ];
            continue;
        }
        $existingSortDate = trim((string) ($existing['sort_date'] ?? ''));
        $replaceExisting = false;
        if ($sortDate !== '' && ($existingSortDate === '' || strcmp($sortDate, $existingSortDate) > 0)) {
            $replaceExisting = true;
        } elseif ($sortDate === $existingSortDate && $dashboardDgStageRank($stage) > (int) ($existing['stage_rank'] ?? 0)) {
            $replaceExisting = true;
        }
        if ($replaceExisting) {
            $dashboardPeopleLastProgressMap[$personId] = [
                'stage' => $stage,
                'sort_date' => $sortDate,
                'stage_rank' => $dashboardDgStageRank($stage),
            ];
        }
    }
    $dashboardPeopleCurrentProgressMap = [];
    foreach ($groups as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $progressLabel = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        if ($progressLabel === '') {
            $progressLabel = '-';
        }
        $memberIds = $groupRow['member_ids'] ?? [];
        if (!is_array($memberIds)) {
            continue;
        }
        foreach ($memberIds as $memberIdRaw) {
            $memberId = trim((string) $memberIdRaw);
            if ($memberId === '') {
                continue;
            }
            if (!isset($dashboardPeopleCurrentProgressMap[$memberId])) {
                $dashboardPeopleCurrentProgressMap[$memberId] = [];
            }
            if (!in_array($progressLabel, $dashboardPeopleCurrentProgressMap[$memberId], true)) {
                $dashboardPeopleCurrentProgressMap[$memberId][] = $progressLabel;
            }
        }
    }
    $dashboardActivePeopleCount = 0;
    foreach ($people as $personRow) {
        if (!is_array($personRow)) {
            continue;
        }
        $personId = trim((string) ($personRow['id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $lastProgressStage = trim((string) ($dashboardPeopleLastProgressMap[$personId]['stage'] ?? ''));
        if ($lastProgressStage === '') {
            continue;
        }
        $currentProgressValues = $dashboardPeopleCurrentProgressMap[$personId] ?? [];
        if (!is_array($currentProgressValues)) {
            $currentProgressValues = [];
        }
        $currentProgressValues = array_values(array_filter(array_map('strval', $currentProgressValues), static function ($value): bool {
            return trim((string) $value) !== '';
        }));
        if (in_array($lastProgressStage, $currentProgressValues, true)) {
            $dashboardActivePeopleCount++;
        }
    }
    $latestReportDateByGroupKey = [];
    foreach ($dgMeetingReports as $reportRow) {
        if (!is_array($reportRow)) {
            continue;
        }
        $groupId = trim((string) ($reportRow['group_id'] ?? ''));
        $groupName = trim((string) ($reportRow['group_name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $leaderId = trim((string) ($reportRow['leader_id'] ?? ''));
        $leaderName = trim((string) ($reportRow['leader_name'] ?? ''));
        if ($leaderName === '') {
            $leaderName = person_label($peopleById, $leaderId, '-');
        }
        $leaderKey = $leaderId !== '' ? ('id:' . $leaderId) : ('name:' . strtolower($leaderName));
        $groupKey = $groupId !== '' ? ('id:' . $groupId) : ('name:' . strtolower($groupName . '|' . $leaderKey));

        $groupProgress = normalize_dg_progress_value((string) ($reportRow['group_progress'] ?? ''));
        if ($groupProgress === '') {
            $groupProgress = 'DG 1';
        }
        $currentGroupProgress = $groupId !== '' ? trim((string) ($dgCurrentGroupProgressById[$groupId] ?? '')) : '';
        if ($groupId !== '' && $currentGroupProgress !== '' && $groupProgress !== $currentGroupProgress) {
            continue;
        }

        $meetingDate = normalize_ymd_date((string) ($reportRow['meeting_date'] ?? ''));
        if ($meetingDate === '') {
            $createdAt = trim((string) ($reportRow['created_at'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt, $createdAtMatch) === 1) {
                $meetingDate = normalize_ymd_date((string) ($createdAtMatch[0] ?? ''));
            }
        }
        if ($meetingDate === '') {
            continue;
        }
        $existingLatest = trim((string) ($latestReportDateByGroupKey[$groupKey] ?? ''));
        if ($existingLatest === '' || strcmp($meetingDate, $existingLatest) > 0) {
            $latestReportDateByGroupKey[$groupKey] = $meetingDate;
        }
    }
    $oneMonthAgoDate = date('Y-m-d', strtotime('-30 days') ?: time());
    $todayTimestamp = strtotime(today_date());
    if ($todayTimestamp === false) {
        $todayTimestamp = time();
    }
    $groupsNoRecentReportRows = [];
    foreach ($groups as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $groupId = trim((string) ($groupRow['id'] ?? ''));
        $groupName = trim((string) ($groupRow['name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $leaderId = trim((string) ($groupRow['leader_id'] ?? ''));
        $leaderName = person_label($peopleById, $leaderId, '-');
        $leaderKey = $leaderId !== '' ? ('id:' . $leaderId) : ('name:' . strtolower($leaderName));
        $groupKey = $groupId !== '' ? ('id:' . $groupId) : ('name:' . strtolower($groupName . '|' . $leaderKey));
        $progressLabel = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        if ($progressLabel === '') {
            $progressLabel = 'DG 1';
        }

        $latestReportDate = trim((string) ($latestReportDateByGroupKey[$groupKey] ?? ''));
        $isNotRecent = $latestReportDate === '' || strcmp($latestReportDate, $oneMonthAgoDate) < 0;
        if (!$isNotRecent) {
            continue;
        }

        $daysSinceReport = null;
        if ($latestReportDate !== '') {
            $latestTimestamp = strtotime($latestReportDate);
            if ($latestTimestamp !== false) {
                $daysSinceReport = (int) floor(max(0, $todayTimestamp - $latestTimestamp) / 86400);
            }
        }
        $lastReportLabel = $latestReportDate !== '' ? format_indo_date($latestReportDate) : 'Belum Pernah Lapor';
        if ($daysSinceReport !== null) {
            $lastReportLabel .= ' (' . (string) $daysSinceReport . ' hari lalu)';
        }
        $memberFirstNames = [];
        $groupMemberIds = $groupRow['member_ids'] ?? [];
        if (is_array($groupMemberIds)) {
            $seenMemberIds = [];
            foreach ($groupMemberIds as $memberIdRaw) {
                $memberId = trim((string) $memberIdRaw);
                if ($memberId === '' || isset($seenMemberIds[$memberId])) {
                    continue;
                }
                $seenMemberIds[$memberId] = true;
                $memberName = trim(person_label($peopleById, $memberId, ''));
                if ($memberName === '' || $memberName === '-') {
                    continue;
                }
                $memberNameParts = preg_split('/\s+/', $memberName);
                if (!is_array($memberNameParts) || count($memberNameParts) === 0) {
                    continue;
                }
                $memberFirstName = trim((string) $memberNameParts[0]);
                if ($memberFirstName === '') {
                    continue;
                }
                $memberFirstNames[] = $memberFirstName;
            }
        }
        $memberFirstNamesLabel = count($memberFirstNames) > 0 ? implode(', ', $memberFirstNames) : 'Belum ada anggota';
        $groupsNoRecentReportRows[] = [
            'group_name' => $groupName,
            'members_first_names_label' => $memberFirstNamesLabel,
            'leader_name' => $leaderName !== '' ? $leaderName : '-',
            'progress' => $progressLabel,
            'last_report_label' => $lastReportLabel,
            'last_report_date' => $latestReportDate,
        ];
    }
    usort($groupsNoRecentReportRows, function (array $a, array $b): int {
        $aDate = trim((string) ($a['last_report_date'] ?? ''));
        $bDate = trim((string) ($b['last_report_date'] ?? ''));
        if ($aDate === '' && $bDate !== '') {
            return -1;
        }
        if ($aDate !== '' && $bDate === '') {
            return 1;
        }
        if ($aDate !== $bDate) {
            return strcmp($aDate, $bDate);
        }
        return strcasecmp((string) ($a['group_name'] ?? ''), (string) ($b['group_name'] ?? ''));
    });
    usort($incompleteMskRows, function (array $a, array $b): int {
        $aMonth = trim((string) ($a['msk_month'] ?? ''));
        $bMonth = trim((string) ($b['msk_month'] ?? ''));
        if ($aMonth !== $bMonth) {
            if ($aMonth === '') {
                return 1;
            }
            if ($bMonth === '') {
                return -1;
            }
            return strcmp($bMonth, $aMonth);
        }
        $aSession = (int) ($a['session_count'] ?? 0);
        $bSession = (int) ($b['session_count'] ?? 0);
        if ($aSession !== $bSession) {
            return $aSession <=> $bSession;
        }
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    $totalGroupsNoRecentReport = count($groupsNoRecentReportRows);

    if ($showDashboardStats) {
        $dashboardSelectedBranch = $centralReadOnly
            ? (isset($centralSelectedBranch) ? normalize_central_recap_branch((string) $centralSelectedBranch) : central_recap_selected_branch())
            : normalize_public_branch_code(current_user_branch());
        $showCentralAllBranchBreakdown = $centralReadOnly && $dashboardSelectedBranch === 'all';
        $discipleshipTargetPeople = max(0, (int) ($discipleshipTargets['dg_total_people'] ?? 50));
        $discipleshipTargetMskCompleted = max(0, (int) ($discipleshipTargets['msk_completed'] ?? 50));
        $discipleshipTargetDg1People = max(0, (int) ($discipleshipTargets['dg1_people'] ?? 50));
        $discipleshipTargetDg2People = max(0, (int) ($discipleshipTargets['dg2_people'] ?? 50));
        $discipleshipTargetDg3People = max(0, (int) ($discipleshipTargets['dg3_people'] ?? 50));
        if (is_effective_central_discipleship_readonly()) {
            $targetSelectedBranch = $dashboardSelectedBranch;
            if ($targetSelectedBranch === 'all') {
                $discipleshipTargetPeople = 0;
                $discipleshipTargetMskCompleted = 0;
                $discipleshipTargetDg1People = 0;
                $discipleshipTargetDg2People = 0;
                $discipleshipTargetDg3People = 0;
                foreach (public_dg_branch_options() as $branchOption) {
                    $branchCode = normalize_public_branch_code((string) ($branchOption['code'] ?? 'kutisari'));
                    $branchTargets = read_branch_discipleship_targets($branchCode);
                    $discipleshipTargetPeople += max(0, (int) ($branchTargets['dg_total_people'] ?? 0));
                    $discipleshipTargetMskCompleted += max(0, (int) ($branchTargets['msk_completed'] ?? 0));
                    $discipleshipTargetDg1People += max(0, (int) ($branchTargets['dg1_people'] ?? 0));
                    $discipleshipTargetDg2People += max(0, (int) ($branchTargets['dg2_people'] ?? 0));
                    $discipleshipTargetDg3People += max(0, (int) ($branchTargets['dg3_people'] ?? 0));
                }
            } else {
                $selectedBranchTargets = read_branch_discipleship_targets($targetSelectedBranch);
                $discipleshipTargetPeople = max(0, (int) ($selectedBranchTargets['dg_total_people'] ?? 50));
                $discipleshipTargetMskCompleted = max(0, (int) ($selectedBranchTargets['msk_completed'] ?? 50));
                $discipleshipTargetDg1People = max(0, (int) ($selectedBranchTargets['dg1_people'] ?? 50));
                $discipleshipTargetDg2People = max(0, (int) ($selectedBranchTargets['dg2_people'] ?? 50));
                $discipleshipTargetDg3People = max(0, (int) ($selectedBranchTargets['dg3_people'] ?? 50));
            }
        }
        $renderTargetProgressLabel = function (int $current, int $target): string {
            if ($target <= 0) {
                return '0%';
            }
            $percent = (max(0, $current) / $target) * 100;
            $percentLabel = number_format($percent, 1, ',', '.');
            if (substr($percentLabel, -2) === ',0') {
                $percentLabel = substr($percentLabel, 0, -2);
            }
            return $percentLabel . '%';
        };
        $completedMskCount = 0;
        $followingKgapCount = 0;
        $followingRgCount = 0;
        $dgMeetingsThisMonth = 0;
        $currentMonthKey = date('Y-m');
        $peopleByMemberIdForJourney = [];
        $peopleByNameForJourney = [];
        foreach ($people as $personRow) {
            if (!is_array($personRow)) {
                continue;
            }
            $personId = trim((string) ($personRow['id'] ?? ''));
            if ($personId === '') {
                continue;
            }
            $personMemberId = trim((string) ($personRow['member_id'] ?? ''));
            if ($personMemberId !== '' && !isset($peopleByMemberIdForJourney[$personMemberId])) {
                $peopleByMemberIdForJourney[$personMemberId] = $personId;
            }
            $personNameKey = strtolower(trim((string) ($personRow['name'] ?? '')));
            if ($personNameKey !== '' && !isset($peopleByNameForJourney[$personNameKey])) {
                $peopleByNameForJourney[$personNameKey] = $personId;
            }
        }
        $dashboardJourneyStageRank = static function (string $stage): int {
            $stage = normalize_dg_progress_value($stage);
            if ($stage === 'DG 3') {
                return 3;
            }
            if ($stage === 'DG 2') {
                return 2;
            }
            if ($stage === 'DG 1') {
                return 1;
            }
            return 0;
        };
        $dashboardCompletedDg1Map = [];
        $dashboardCompletedDg2Map = [];
        $dashboardCompletedDg3Map = [];
        foreach (($discipleshipV2Model['group_memberships'] ?? []) as $membershipRecord) {
            if (!is_array($membershipRecord)) {
                continue;
            }
            $personId = trim((string) ($membershipRecord['person_id'] ?? ''));
            if ($personId === '') {
                continue;
            }
            $stage = normalize_dg_progress_value((string) ($membershipRecord['stage'] ?? ''));
            if ($stage === '') {
                continue;
            }
            $stageRank = $dashboardJourneyStageRank($stage);
            $reasonEnd = trim((string) ($membershipRecord['reason_end'] ?? ''));
            if (
                $stageRank >= 2
                || ($stage === 'DG 1' && in_array($reasonEnd, ['continued_to_child_group', 'group_completed', 'stage_transition'], true))
            ) {
                $dashboardCompletedDg1Map[$personId] = true;
            }
            if (
                $stageRank >= 3
                || ($stage === 'DG 2' && in_array($reasonEnd, ['continued_to_child_group', 'group_completed', 'stage_transition'], true))
            ) {
                $dashboardCompletedDg2Map[$personId] = true;
            }
            if (
                $stage === 'DG 3'
                && in_array($reasonEnd, ['group_completed', 'continued_to_child_group', 'stage_transition'], true)
            ) {
                $dashboardCompletedDg3Map[$personId] = true;
            }
        }
        foreach ($mskClasses as $participant) {
            if (!is_array($participant)) {
                continue;
            }
            if (count(normalize_msk_session_numbers($participant['session_numbers'] ?? [])) >= 12) {
                $completedMskCount++;
            }
            $journeyBridgeStatus = normalize_journey_bridge_status((string) ($participant['journey_bridge_status'] ?? 'belum'));
            if (in_array($journeyBridgeStatus, ['sudah_kgap', 'ikut_keduanya'], true)) {
                $followingKgapCount++;
            }
            if (in_array($journeyBridgeStatus, ['sudah_rg', 'ikut_keduanya'], true)) {
                $followingRgCount++;
            }
        }
        foreach ($dgMeetingReports as $reportRow) {
            if (!is_array($reportRow)) {
                continue;
            }
            $meetingDate = normalize_ymd_date((string) ($reportRow['meeting_date'] ?? ''));
            if ($meetingDate !== '' && substr($meetingDate, 0, 7) === $currentMonthKey) {
                $dgMeetingsThisMonth++;
            }
        }
        $completedDg1Count = 0;
        $completedDg2Count = 0;
        $completedDg3Count = 0;
        foreach ($mskClasses as $participant) {
            if (!is_array($participant)) {
                continue;
            }
            $participantName = trim((string) ($participant['full_name'] ?? ''));
            if ($participantName === '') {
                continue;
            }
            $participantMemberId = trim((string) ($participant['member_id'] ?? ''));
            $resolvedPersonId = '';
            if ($participantMemberId !== '' && isset($peopleByMemberIdForJourney[$participantMemberId])) {
                $resolvedPersonId = (string) $peopleByMemberIdForJourney[$participantMemberId];
            } else {
                $participantNameKey = strtolower($participantName);
                if ($participantNameKey !== '' && isset($peopleByNameForJourney[$participantNameKey])) {
                    $resolvedPersonId = (string) $peopleByNameForJourney[$participantNameKey];
                }
            }
            if ($resolvedPersonId === '') {
                continue;
            }
            if (!empty($dashboardCompletedDg1Map[$resolvedPersonId])) {
                $completedDg1Count++;
            }
            if (!empty($dashboardCompletedDg2Map[$resolvedPersonId])) {
                $completedDg2Count++;
            }
            if (!empty($dashboardCompletedDg3Map[$resolvedPersonId])) {
                $completedDg3Count++;
            }
        }
        $journeyProgressRows = [
            ['label' => 'Selesai MSK', 'value' => $completedMskCount, 'target' => $discipleshipTargetMskCompleted, 'color' => '#0f766e'],
            ['label' => 'Selesai DG 1', 'value' => $completedDg1Count, 'target' => $discipleshipTargetDg1People, 'color' => discipleship_stage_color('DG 1')],
            ['label' => 'Selesai Kamp GAP', 'value' => $followingKgapCount, 'target' => $discipleshipTargetPeople, 'color' => '#0ea5e9'],
            ['label' => 'Selesai DG 2', 'value' => $completedDg2Count, 'target' => $discipleshipTargetDg2People, 'color' => discipleship_stage_color('DG 2')],
            ['label' => 'Selesai DG 3', 'value' => $completedDg3Count, 'target' => $discipleshipTargetDg3People, 'color' => discipleship_stage_color('DG 3')],
        ];
        $activeGroupProgressRows = [];
        foreach ($dgProgressChartRows as $progressRow) {
            $stageLabel = trim((string) ($progressRow['label'] ?? '-'));
            if ($stageLabel === '') {
                $stageLabel = '-';
            }
            $activeGroupProgressRows[] = [
                'label' => $stageLabel . ' Berjalan',
                'value' => max(0, (int) ($progressRow['value'] ?? 0)),
                'target' => max(0, (int) $kelompokCount),
                'color' => trim((string) ($progressRow['color'] ?? discipleship_stage_color($stageLabel))),
            ];
        }
        $overallProgressTotal = 0.0;
        foreach ($journeyProgressRows as $progressRow) {
            $progressTarget = max(0, (int) ($progressRow['target'] ?? 0));
            $progressValue = max(0, (int) ($progressRow['value'] ?? 0));
            $overallProgressTotal += $progressTarget > 0 ? min(100, ($progressValue / $progressTarget) * 100) : 0;
        }
        $overallProgressAverage = count($journeyProgressRows) > 0 ? ($overallProgressTotal / count($journeyProgressRows)) : 0.0;
        $overallProgressLabel = number_format($overallProgressAverage, 1, ',', '.');
        if (substr($overallProgressLabel, -2) === ',0') {
            $overallProgressLabel = substr($overallProgressLabel, 0, -2);
        }
        $dashboardDataStats = [
            ['label' => 'Peserta Aktif', 'value' => number_format((int) $dashboardActivePeopleCount, 0, ',', '.'), 'sub' => 'Anggota yang sedang didalam kelompok aktif', 'tone' => 'is-primary'],
            ['label' => 'Pemimpin Aktif', 'value' => number_format((int) $totalLeaders, 0, ',', '.'), 'sub' => 'Total pemimpin yang sedang memimpin kelompok aktif', 'tone' => 'is-emerald'],
            ['label' => 'Kelompok Aktif', 'value' => number_format((int) $kelompokCount, 0, ',', '.'), 'sub' => 'Total kelompok yang sedang berjalan', 'tone' => 'is-dg2'],
            ['label' => 'Pertemuan Bulan Ini', 'value' => number_format((int) $dgMeetingsThisMonth, 0, ',', '.'), 'sub' => 'Laporan pertemuan DG di bulan berjalan', 'tone' => 'is-amber'],
            ['label' => 'Selesai RG', 'value' => number_format((int) $followingRgCount, 0, ',', '.'), 'sub' => 'Peserta dengan status RG atau ikut keduanya', 'tone' => 'is-sky'],
            ['label' => 'Belum Lapor DG 30 Hari', 'value' => number_format((int) $totalGroupsNoRecentReport, 0, ',', '.'), 'sub' => 'Kelompok yang perlu segera ditindaklanjuti', 'tone' => 'is-rose'],
            ['label' => 'Belum Selesai MSK', 'value' => number_format((int) $totalIncompleteMsk, 0, ',', '.'), 'sub' => 'Peserta masih berjalan menuju 12 sesi', 'tone' => 'is-slate'],
        ];
        $dashboardBranchDetailRows = [];
        if ($showCentralAllBranchBreakdown) {
            $branchMetrics = [];
            foreach (central_recap_branch_options() as $branchOption) {
                $branchCode = normalize_central_recap_branch((string) ($branchOption['code'] ?? 'all'));
                if ($branchCode === 'all') {
                    continue;
                }
                $branchLabel = trim((string) ($branchOption['label'] ?? strtoupper($branchCode)));
                if ($branchLabel === '') {
                    $branchLabel = strtoupper($branchCode);
                }
                $branchMetrics[$branchCode] = [
                    'label' => $branchLabel,
                    'people_count' => 0,
                    'active_member_count' => 0,
                    'active_member_count_map' => [],
                    'leader_ids' => [],
                    'group_count' => 0,
                    'meeting_count' => 0,
                    'following_rg_count' => 0,
                    'following_kgap_count' => 0,
                    'completed_msk_count' => 0,
                    'completed_dg1_count' => 0,
                    'completed_dg2_count' => 0,
                    'completed_dg3_count' => 0,
                    'target_people' => 0,
                    'target_msk_completed' => 0,
                    'target_dg1_people' => 0,
                    'target_dg2_people' => 0,
                    'target_dg3_people' => 0,
                ];
                $branchTargets = read_branch_discipleship_targets($branchCode);
                $branchMetrics[$branchCode]['target_people'] = max(0, (int) ($branchTargets['dg_total_people'] ?? 0));
                $branchMetrics[$branchCode]['target_msk_completed'] = max(0, (int) ($branchTargets['msk_completed'] ?? 0));
                $branchMetrics[$branchCode]['target_dg1_people'] = max(0, (int) ($branchTargets['dg1_people'] ?? 0));
                $branchMetrics[$branchCode]['target_dg2_people'] = max(0, (int) ($branchTargets['dg2_people'] ?? 0));
                $branchMetrics[$branchCode]['target_dg3_people'] = max(0, (int) ($branchTargets['dg3_people'] ?? 0));
            }
            foreach ($people as $personRow) {
                if (!is_array($personRow)) {
                    continue;
                }
                $branchCode = normalize_public_branch_code((string) ($personRow['branch_code'] ?? ''));
                if (!isset($branchMetrics[$branchCode])) {
                    continue;
                }
                $branchMetrics[$branchCode]['people_count']++;
            }
            foreach ($groups as $groupRow) {
                if (!is_array($groupRow)) {
                    continue;
                }
                $branchCode = normalize_public_branch_code((string) ($groupRow['branch_code'] ?? ''));
                if (!isset($branchMetrics[$branchCode])) {
                    continue;
                }
                $branchMetrics[$branchCode]['group_count']++;
                $leaderId = trim((string) ($groupRow['leader_id'] ?? ''));
                if ($leaderId !== '') {
                    $branchMetrics[$branchCode]['leader_ids'][$leaderId] = true;
                }
                $memberIds = $groupRow['member_ids'] ?? [];
                if (is_array($memberIds)) {
                    foreach ($memberIds as $memberIdRaw) {
                        $memberId = trim((string) $memberIdRaw);
                        if ($memberId === '') {
                            continue;
                        }
                        $branchMetrics[$branchCode]['active_member_count_map'][$memberId] = true;
                    }
                }
            }
            foreach ($dgMeetingReports as $reportRow) {
                if (!is_array($reportRow)) {
                    continue;
                }
                $branchCode = normalize_public_branch_code((string) ($reportRow['branch_code'] ?? ''));
                if (!isset($branchMetrics[$branchCode])) {
                    continue;
                }
                $meetingDate = normalize_ymd_date((string) ($reportRow['meeting_date'] ?? ''));
                if ($meetingDate !== '' && substr($meetingDate, 0, 7) === $currentMonthKey) {
                    $branchMetrics[$branchCode]['meeting_count']++;
                }
            }
            foreach ($mskClasses as $participantRow) {
                if (!is_array($participantRow)) {
                    continue;
                }
                $branchCode = normalize_public_branch_code((string) ($participantRow['branch_code'] ?? ''));
                if (!isset($branchMetrics[$branchCode])) {
                    continue;
                }
                if (count(normalize_msk_session_numbers($participantRow['session_numbers'] ?? [])) >= 12) {
                    $branchMetrics[$branchCode]['completed_msk_count']++;
                }
                $journeyBridgeStatus = normalize_journey_bridge_status((string) ($participantRow['journey_bridge_status'] ?? 'belum'));
                if (in_array($journeyBridgeStatus, ['sudah_rg', 'ikut_keduanya'], true)) {
                    $branchMetrics[$branchCode]['following_rg_count']++;
                }
                if (in_array($journeyBridgeStatus, ['sudah_kgap', 'ikut_keduanya'], true)) {
                    $branchMetrics[$branchCode]['following_kgap_count']++;
                }
                $participantName = trim((string) ($participantRow['full_name'] ?? ''));
                if ($participantName === '') {
                    continue;
                }
                $participantMemberId = trim((string) ($participantRow['member_id'] ?? ''));
                $resolvedPersonId = '';
                if ($participantMemberId !== '' && isset($peopleByMemberIdForJourney[$participantMemberId])) {
                    $resolvedPersonId = (string) $peopleByMemberIdForJourney[$participantMemberId];
                } else {
                    $participantNameKey = strtolower($participantName);
                    if ($participantNameKey !== '' && isset($peopleByNameForJourney[$participantNameKey])) {
                        $resolvedPersonId = (string) $peopleByNameForJourney[$participantNameKey];
                    }
                }
                if ($resolvedPersonId === '' || !isset($peopleById[$resolvedPersonId])) {
                    continue;
                }
                $resolvedBranchCode = normalize_public_branch_code((string) ($peopleById[$resolvedPersonId]['branch_code'] ?? $branchCode));
                if (!isset($branchMetrics[$resolvedBranchCode])) {
                    continue;
                }
                if (!empty($dashboardCompletedDg1Map[$resolvedPersonId])) {
                    $branchMetrics[$resolvedBranchCode]['completed_dg1_count']++;
                }
                if (!empty($dashboardCompletedDg2Map[$resolvedPersonId])) {
                    $branchMetrics[$resolvedBranchCode]['completed_dg2_count']++;
                }
                if (!empty($dashboardCompletedDg3Map[$resolvedPersonId])) {
                    $branchMetrics[$resolvedBranchCode]['completed_dg3_count']++;
                }
            }
            foreach ($branchMetrics as $branchCode => $metricRow) {
                $leaderCount = count($metricRow['leader_ids'] ?? []);
                $activeMemberCount = count($metricRow['active_member_count_map'] ?? []);
                $branchProgressRows = [
                    ['value' => (int) ($metricRow['completed_msk_count'] ?? 0), 'target' => (int) ($metricRow['target_msk_completed'] ?? 0)],
                    ['value' => (int) ($metricRow['completed_dg1_count'] ?? 0), 'target' => (int) ($metricRow['target_dg1_people'] ?? 0)],
                    ['value' => (int) ($metricRow['following_kgap_count'] ?? 0), 'target' => (int) ($metricRow['target_people'] ?? 0)],
                    ['value' => (int) ($metricRow['completed_dg2_count'] ?? 0), 'target' => (int) ($metricRow['target_dg2_people'] ?? 0)],
                    ['value' => (int) ($metricRow['completed_dg3_count'] ?? 0), 'target' => (int) ($metricRow['target_dg3_people'] ?? 0)],
                ];
                $branchProgressTotal = 0.0;
                foreach ($branchProgressRows as $branchProgressRow) {
                    $branchProgressTarget = max(0, (int) ($branchProgressRow['target'] ?? 0));
                    $branchProgressValue = max(0, (int) ($branchProgressRow['value'] ?? 0));
                    $branchProgressTotal += $branchProgressTarget > 0 ? min(100, ($branchProgressValue / $branchProgressTarget) * 100) : 0;
                }
                $branchProgressAverage = count($branchProgressRows) > 0 ? ($branchProgressTotal / count($branchProgressRows)) : 0.0;
                $dashboardBranchDetailRows[] = [
                    'branch_label' => (string) ($metricRow['label'] ?? strtoupper($branchCode)),
                    'people_count' => (int) ($metricRow['people_count'] ?? 0),
                    'active_member_count' => $activeMemberCount,
                    'leader_count' => $leaderCount,
                    'group_count' => (int) ($metricRow['group_count'] ?? 0),
                    'meeting_count' => (int) ($metricRow['meeting_count'] ?? 0),
                    'following_rg_count' => (int) ($metricRow['following_rg_count'] ?? 0),
                    'completed_msk_count' => (int) ($metricRow['completed_msk_count'] ?? 0),
                    'completed_dg1_count' => (int) ($metricRow['completed_dg1_count'] ?? 0),
                    'completed_dg2_count' => (int) ($metricRow['completed_dg2_count'] ?? 0),
                    'completed_dg3_count' => (int) ($metricRow['completed_dg3_count'] ?? 0),
                    'following_kgap_count' => (int) ($metricRow['following_kgap_count'] ?? 0),
                    'target_people' => (int) ($metricRow['target_people'] ?? 0),
                    'target_msk_completed' => (int) ($metricRow['target_msk_completed'] ?? 0),
                    'target_dg1_people' => (int) ($metricRow['target_dg1_people'] ?? 0),
                    'target_dg2_people' => (int) ($metricRow['target_dg2_people'] ?? 0),
                    'target_dg3_people' => (int) ($metricRow['target_dg3_people'] ?? 0),
                    'overall_progress_percent' => $branchProgressAverage,
                ];
            }
            usort($dashboardBranchDetailRows, static function (array $a, array $b): int {
                return strcasecmp((string) ($a['branch_label'] ?? ''), (string) ($b['branch_label'] ?? ''));
            });
        }
        $activeBranchLabel = user_branch_label(current_user_branch());

        echo "<section class=\"card discipleship-dashboard-hero-card\">\n";
        echo "  <div class=\"discipleship-dashboard-hero-head\">\n";
        echo "    <div class=\"discipleship-dashboard-hero-copy\">\n";
        echo "      <span class=\"discipleship-dashboard-hero-kicker\">Dashboard Pemuridan</span>\n";
        echo "      <h2>Monitor Pemuridan <span class=\"discipleship-dashboard-hero-branch\">REC " . h($activeBranchLabel) . "</span></h2>\n";
        echo "      <p>Ringkasan ini merangkum capaian target, kesehatan kelompok, dan area yang perlu segera direspons supaya pergerakan pemuridan tetap konsisten.</p>\n";
        echo "      <p class=\"discipleship-dashboard-hero-actions\"><a class=\"btn\" href=\"?page=public_links\">Buka Halaman Public Link</a></p>\n";
        echo "    </div>\n";
        echo "    <div class=\"discipleship-dashboard-hero-summary\">\n";
        echo "      <div class=\"discipleship-dashboard-hero-summary-ring\" style=\"--pct:" . h((string) $overallProgressAverage) . ";\"><span>" . h($overallProgressLabel) . "%</span></div>\n";
        echo "      <div class=\"discipleship-dashboard-hero-summary-copy\">\n";
        echo "        <span class=\"discipleship-dashboard-hero-summary-label\">Rata-rata Achievement</span>\n";
        echo "        <strong class=\"discipleship-dashboard-hero-summary-value\">" . h($overallProgressLabel) . "%</strong>\n";
        echo "        <span class=\"discipleship-dashboard-hero-summary-sub\">Gabungan capaian Selesai MSK, Selesai DG 1, Selesai Kamp GAP, Selesai DG 2, dan Selesai DG 3.</span>\n";
        echo "      </div>\n";
        echo "    </div>\n";
        echo "  </div>\n";
        echo "</section>\n";

        echo "<section class=\"card discipleship-dashboard-progress-card\">\n";
        echo "  <div class=\"card-row discipleship-dashboard-section-head\"><h2>Achievement Target</h2><span class=\"badge muted\">Live dari data pemuridan</span></div>\n";
        echo "  <div class=\"journey-progress-grid journey-progress-grid-standalone\">\n";
        foreach ($journeyProgressRows as $row) {
            $value = max(0, (int) ($row['value'] ?? 0));
            $target = max(0, (int) ($row['target'] ?? 0));
            $color = trim((string) ($row['color'] ?? '#0f766e'));
            $label = trim((string) ($row['label'] ?? '-'));
            $percent = $target > 0 ? min(100, ($value / $target) * 100) : 0;
            $percentLabel = number_format($percent, 1, ',', '.');
            if (substr($percentLabel, -2) === ',0') {
                $percentLabel = substr($percentLabel, 0, -2);
            }
            echo "    <div class=\"journey-progress-chip\">\n";
            echo "      <div class=\"journey-progress-ring\" style=\"--pct:" . h((string) $percent) . ";--ring-color:" . h($color) . ";\"><span>" . h($percentLabel) . "%</span></div>\n";
            echo "      <div class=\"journey-progress-copy\"><div class=\"journey-progress-label\">" . h($label) . "</div><div class=\"journey-progress-value\">" . h(number_format($value, 0, ',', '.')) . " / " . h(number_format($target, 0, ',', '.')) . "</div></div>\n";
            echo "    </div>\n";
        }
        echo "  </div>\n";
        echo "  <div class=\"card-row discipleship-dashboard-section-head\"><h2>Kelompok Berjalan</h2><span class=\"badge muted\">Kelompok aktif saja</span></div>\n";
        if ($kelompokCount <= 0) {
            echo "  <div class=\"chart-empty-inline\">Belum ada kelompok aktif yang sedang berjalan.</div>\n";
        } else {
            echo "  <div class=\"journey-progress-grid journey-progress-grid-standalone\">\n";
            foreach ($activeGroupProgressRows as $row) {
                $value = max(0, (int) ($row['value'] ?? 0));
                $target = max(0, (int) ($row['target'] ?? 0));
                $color = trim((string) ($row['color'] ?? '#0f766e'));
                $label = trim((string) ($row['label'] ?? '-'));
                $percent = $target > 0 ? min(100, ($value / $target) * 100) : 0;
                $percentLabel = number_format($percent, 1, ',', '.');
                if (substr($percentLabel, -2) === ',0') {
                    $percentLabel = substr($percentLabel, 0, -2);
                }
                echo "    <div class=\"journey-progress-chip\">\n";
                echo "      <div class=\"journey-progress-ring\" style=\"--pct:" . h((string) $percent) . ";--ring-color:" . h($color) . ";\"><span>" . h($percentLabel) . "%</span></div>\n";
                echo "      <div class=\"journey-progress-copy\"><div class=\"journey-progress-label\">" . h($label) . "</div><div class=\"journey-progress-value\">" . h(number_format($value, 0, ',', '.')) . " / " . h(number_format($target, 0, ',', '.')) . " kelompok aktif</div></div>\n";
                echo "    </div>\n";
            }
            echo "  </div>\n";
        }
        echo "  <div class=\"discipleship-dashboard-data-stats\">\n";
        foreach ($dashboardDataStats as $dataStat) {
            $tone = trim((string) ($dataStat['tone'] ?? ''));
            $toneClass = $tone !== '' ? ' ' . $tone : '';
            echo "    <article class=\"discipleship-dashboard-data-stat" . h($toneClass) . "\">\n";
            echo "      <span class=\"discipleship-dashboard-data-stat-label\">" . h((string) ($dataStat['label'] ?? '-')) . "</span>\n";
            echo "      <strong class=\"discipleship-dashboard-data-stat-value\">" . h((string) ($dataStat['value'] ?? '0')) . "</strong>\n";
            echo "      <span class=\"discipleship-dashboard-data-stat-sub\">" . h((string) ($dataStat['sub'] ?? '')) . "</span>\n";
            echo "    </article>\n";
        }
        echo "  </div>\n";
        echo "</section>\n";

        $renderDiscipleshipPieChart = function (string $title, string $ariaLabel, string $centerLabel, array $rows): void {
            $total = 0;
            foreach ($rows as $row) {
                $total += max(0, (int) ($row['value'] ?? 0));
            }

            echo "  <article class=\"card member-pie-card\">\n";
            echo "    <div class=\"card-row\"><h2>" . h($title) . "</h2></div>\n";
            if ($total <= 0) {
                echo "    <div class=\"chart-empty-inline\">Belum ada data untuk ditampilkan.</div>\n";
                echo "  </article>\n";
                return;
            }

            $size = 220;
            $center = $size / 2;
            $radius = 74;
            $circumference = 2 * pi() * $radius;
            $offset = 0.0;

            echo "    <div class=\"member-pie-layout\">\n";
            echo "      <div class=\"member-pie-stage\" role=\"img\" aria-label=\"" . h($ariaLabel) . "\">\n";
            echo "        <svg class=\"member-pie-svg\" viewBox=\"0 0 " . h((string) $size) . " " . h((string) $size) . "\">\n";
            echo "          <circle class=\"member-pie-track\" cx=\"" . h((string) $center) . "\" cy=\"" . h((string) $center) . "\" r=\"" . h((string) $radius) . "\"></circle>\n";
            echo "          <g transform=\"rotate(-90 " . h((string) $center) . " " . h((string) $center) . ")\">\n";
            foreach ($rows as $row) {
                $value = max(0, (int) ($row['value'] ?? 0));
                if ($value <= 0) {
                    continue;
                }
                $portion = $value / $total;
                $length = $portion * $circumference;
                $dash = number_format($length, 2, '.', '') . ' ' . number_format(max($circumference - $length, 0), 2, '.', '');
                $dashOffset = number_format(-$offset, 2, '.', '');
                $offset += $length;
                $stroke = trim((string) ($row['color'] ?? '#94a3b8'));
                if ($stroke === '') {
                    $stroke = '#94a3b8';
                }
                $label = trim((string) ($row['label'] ?? '-'));
                if ($label === '') {
                    $label = '-';
                }
                $segmentPercent = ($value / $total) * 100;
                $segmentPercentLabel = number_format($segmentPercent, 1, ',', '.');
                $segmentTip = $label . ': ' . $value . ' (' . $segmentPercentLabel . '%)';
                echo "            <circle class=\"member-pie-segment\" cx=\"" . h((string) $center) . "\" cy=\"" . h((string) $center) . "\" r=\"" . h((string) $radius) . "\" stroke=\"" . h($stroke) . "\" stroke-dasharray=\"" . h($dash) . "\" stroke-dashoffset=\"" . h($dashOffset) . "\" tabindex=\"0\" aria-label=\"" . h($segmentTip) . "\" data-member-pie-segment-tip=\"" . h($segmentTip) . "\"><title>" . h($segmentTip) . "</title></circle>\n";
            }
            echo "          </g>\n";
            echo "        </svg>\n";
            echo "        <div class=\"member-pie-center\"><span class=\"value\">" . h((string) $total) . "</span><span class=\"label\">" . h($centerLabel) . "</span></div>\n";
            echo "        <div class=\"member-pie-tooltip\" data-member-pie-tooltip></div>\n";
            echo "      </div>\n";
            echo "      <div class=\"member-pie-legend\">\n";
            foreach ($rows as $row) {
                $value = max(0, (int) ($row['value'] ?? 0));
                $percent = $total > 0 ? ($value / $total) * 100 : 0.0;
                $percentLabel = number_format($percent, 1, ',', '.');
                $label = trim((string) ($row['label'] ?? '-'));
                if ($label === '') {
                    $label = '-';
                }
                $color = trim((string) ($row['color'] ?? '#94a3b8'));
                if ($color === '') {
                    $color = '#94a3b8';
                }
                echo "        <div class=\"member-pie-legend-item\"><span class=\"dot\" style=\"background:" . h($color) . ";\"></span><span class=\"text\">" . h($label) . "</span><span class=\"count\">" . h((string) $value) . " (" . h($percentLabel) . "%)</span></div>\n";
            }
            echo "      </div>\n";
            echo "    </div>\n";
            echo "  </article>\n";
        };
        if ($showCentralAllBranchBreakdown) {
            echo "<style>\n";
            echo ".discipleship-branch-breakdown{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;}\n";
            echo ".discipleship-branch-card{position:relative;overflow:hidden;padding:20px;border-radius:24px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,250,252,.96));border:1px solid rgba(148,163,184,.22);box-shadow:0 18px 40px rgba(15,23,42,.08);}\n";
            echo ".discipleship-branch-card::before{content:'';position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,#0f766e,#0ea5e9,#f59e0b);opacity:.92;}\n";
            echo ".discipleship-branch-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:16px;}\n";
            echo ".discipleship-branch-card-title{display:flex;flex-direction:column;gap:6px;min-width:0;}\n";
            echo ".discipleship-branch-card-title h3{margin:0;font-size:20px;line-height:1.2;color:#0f172a;}\n";
            echo ".discipleship-branch-card-title p{margin:0;color:#475569;font-size:13px;line-height:1.5;}\n";
            echo ".discipleship-branch-progress-ring{--pct:0;position:relative;flex:0 0 auto;display:grid;place-items:center;width:78px;height:78px;border-radius:999px;background:conic-gradient(#0f766e calc(var(--pct) * 1%),rgba(148,163,184,.18) 0);}\n";
            echo ".discipleship-branch-progress-ring::after{content:'';position:absolute;inset:8px;border-radius:999px;background:#fff;box-shadow:inset 0 1px 0 rgba(255,255,255,.8);}\n";
            echo ".discipleship-branch-progress-ring strong{position:relative;z-index:1;font-size:17px;line-height:1;font-weight:800;color:#0f172a;}\n";
            echo ".discipleship-branch-targets{display:grid;gap:10px;}\n";
            echo ".discipleship-branch-target{display:grid;gap:6px;background:transparent;border:0;padding:0;box-shadow:none;}\n";
            echo ".discipleship-branch-target-top{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:12px;color:#334155;}\n";
            echo ".discipleship-branch-target-top strong{font-size:12px;color:#0f172a;}\n";
            echo ".discipleship-branch-target-bar{height:10px;border-radius:999px;background:rgba(148,163,184,.16);overflow:hidden;}\n";
            echo ".discipleship-branch-target-bar span{display:block;height:100%;border-radius:inherit;min-width:10px;}\n";
            echo ".discipleship-branch-target.is-msk .discipleship-branch-target-bar span{background:linear-gradient(90deg,#0f766e,#14b8a6);}\n";
            echo ".discipleship-branch-target.is-dg1 .discipleship-branch-target-bar span{background:linear-gradient(90deg,#84cc16,#65a30d);}\n";
            echo ".discipleship-branch-target.is-kgap .discipleship-branch-target-bar span{background:linear-gradient(90deg,#0ea5e9,#2563eb);}\n";
            echo ".discipleship-branch-target.is-dg2 .discipleship-branch-target-bar span{background:linear-gradient(90deg,#f59e0b,#ea580c);}\n";
            echo ".discipleship-branch-target.is-dg3 .discipleship-branch-target-bar span{background:linear-gradient(90deg,#ef4444,#dc2626);}\n";
            echo "@media (max-width: 640px){.discipleship-branch-card{padding:18px;}.discipleship-branch-card-head{align-items:center;}.discipleship-branch-progress-ring{width:70px;height:70px;}}\n";
            echo "</style>\n";
            echo "<section class=\"card\">\n";
            echo "  <div class=\"card-row discipleship-dashboard-section-head\"><h2>Rincian Tiap Cabang</h2><span class=\"badge muted\">Khusus tampilan semua cabang</span></div>\n";
            echo "  <div class=\"discipleship-branch-breakdown\">\n";
            foreach ($dashboardBranchDetailRows as $branchDetailRow) {
                $branchLabel = trim((string) ($branchDetailRow['branch_label'] ?? '-'));
                if ($branchLabel === '') {
                    $branchLabel = '-';
                }
                $branchOverallProgress = max(0, min(100, (float) ($branchDetailRow['overall_progress_percent'] ?? 0)));
                $branchOverallProgressLabel = number_format($branchOverallProgress, 1, ',', '.');
                if (substr($branchOverallProgressLabel, -2) === ',0') {
                    $branchOverallProgressLabel = substr($branchOverallProgressLabel, 0, -2);
                }
                $branchTargetProgressRows = [
                    [
                        'label' => 'Selesai MSK',
                        'value' => (int) ($branchDetailRow['completed_msk_count'] ?? 0),
                        'target' => (int) ($branchDetailRow['target_msk_completed'] ?? 0),
                        'class' => 'is-msk',
                    ],
                    [
                        'label' => 'Selesai DG 1',
                        'value' => (int) ($branchDetailRow['completed_dg1_count'] ?? 0),
                        'target' => (int) ($branchDetailRow['target_dg1_people'] ?? 0),
                        'class' => 'is-dg1',
                    ],
                    [
                        'label' => 'Selesai Kamp GAP',
                        'value' => (int) ($branchDetailRow['following_kgap_count'] ?? 0),
                        'target' => (int) ($branchDetailRow['target_people'] ?? 0),
                        'class' => 'is-kgap',
                    ],
                    [
                        'label' => 'Selesai DG 2',
                        'value' => (int) ($branchDetailRow['completed_dg2_count'] ?? 0),
                        'target' => (int) ($branchDetailRow['target_dg2_people'] ?? 0),
                        'class' => 'is-dg2',
                    ],
                    [
                        'label' => 'Selesai DG 3',
                        'value' => (int) ($branchDetailRow['completed_dg3_count'] ?? 0),
                        'target' => (int) ($branchDetailRow['target_dg3_people'] ?? 0),
                        'class' => 'is-dg3',
                    ],
                ];
                echo "    <article class=\"discipleship-branch-card\">\n";
                echo "      <div class=\"discipleship-branch-card-head\">\n";
                echo "        <div class=\"discipleship-branch-card-title\"><span class=\"badge warning\">Cabang</span><h3>" . h($branchLabel) . "</h3><p>Ringkasan performa cabang dari statistik dashboard dan target pemuridan.</p></div>\n";
                echo "        <div class=\"discipleship-branch-progress-ring\" style=\"--pct:" . h((string) $branchOverallProgress) . ";\"><strong>" . h($branchOverallProgressLabel) . "%</strong></div>\n";
                echo "      </div>\n";
                echo "      <div class=\"discipleship-branch-targets\">\n";
                foreach ($branchTargetProgressRows as $branchTargetProgressRow) {
                    $progressValue = max(0, (int) ($branchTargetProgressRow['value'] ?? 0));
                    $progressTarget = max(0, (int) ($branchTargetProgressRow['target'] ?? 0));
                    $progressPercent = $progressTarget > 0 ? min(100, ($progressValue / $progressTarget) * 100) : 0;
                    $progressPercentLabel = number_format($progressPercent, 1, ',', '.');
                    if (substr($progressPercentLabel, -2) === ',0') {
                        $progressPercentLabel = substr($progressPercentLabel, 0, -2);
                    }
                    echo "        <div class=\"discipleship-branch-target " . h((string) ($branchTargetProgressRow['class'] ?? '')) . "\">\n";
                    echo "          <div class=\"discipleship-branch-target-top\"><span>" . h((string) ($branchTargetProgressRow['label'] ?? '-')) . "</span><strong>" . h(number_format($progressValue, 0, ',', '.')) . " / " . h(number_format($progressTarget, 0, ',', '.')) . " • " . h($progressPercentLabel) . "%</strong></div>\n";
                    echo "          <div class=\"discipleship-branch-target-bar\"><span style=\"width:" . h((string) $progressPercent) . "%\"></span></div>\n";
                    echo "        </div>\n";
                }
                echo "      </div>\n";
                echo "    </article>\n";
            }
            echo "  </div>\n";
            echo "</section>\n";
        } else {
            echo "<section class=\"member-pie-grid discipleship-progress-overdue-grid\">\n";
            echo "  <article class=\"card member-pie-card discipleship-overdue-card is-msk\">\n";
            echo "    <div class=\"discipleship-overdue-head\"><div><span class=\"discipleship-overdue-kicker\">Tindak Lanjut MSK</span><h2>Belum Selesai MSK</h2><p>Peserta yang masih perlu pemantauan lanjutan.</p></div><span class=\"discipleship-overdue-count\">" . h(number_format((int) count($incompleteMskRows), 0, ',', '.')) . "</span></div>\n";
            if (count($incompleteMskRows) === 0) {
                echo "    <div class=\"chart-empty-inline\">Semua peserta sudah menyelesaikan 12 sesi MSK.</div>\n";
            } else {
                echo "    <div class=\"discipleship-overdue-list-wrap\">\n";
                echo "      <div class=\"discipleship-overdue-list\">\n";
                foreach ($incompleteMskRows as $row) {
                    $participantName = trim((string) ($row['name'] ?? '-'));
                    if ($participantName === '') {
                        $participantName = '-';
                    }
                    $progressLabel = trim((string) ($row['progress_label'] ?? '0/12 sesi'));
                    if ($progressLabel === '') {
                        $progressLabel = '0/12 sesi';
                    }
                    $phoneLabel = trim((string) ($row['phone'] ?? '-'));
                    if ($phoneLabel === '') {
                        $phoneLabel = '-';
                    }
                    $branchLabel = trim((string) ($row['branch_label'] ?? ''));
                    $participantId = trim((string) ($row['participant_id'] ?? ''));
                    $mskMonthLabel = trim((string) ($row['msk_month_label'] ?? '-'));
                    if ($mskMonthLabel === '') {
                        $mskMonthLabel = '-';
                    }
                    $editButtonHtml = '';
                    if (!$centralReadOnly && $participantId !== '' && isset($incompleteMskEditTemplates[$participantId])) {
                        $editButtonHtml = "<button class=\"btn tiny secondary icon-btn\" type=\"button\" data-msk-edit-open=\"" . h($participantId) . "\" aria-label=\"Edit sesi MSK\" title=\"Edit sesi MSK\">" . icon_svg('edit') . "</button>";
                    }
                    echo "        <div class=\"discipleship-overdue-item\">";
                    echo "<div class=\"discipleship-overdue-top\"><span class=\"name\">" . h($participantName) . "</span><span class=\"discipleship-overdue-actions\"><span class=\"badge warning\">" . h($progressLabel) . "</span>" . $editButtonHtml . "</span></div>";
                    if ($branchLabel !== '') {
                        echo "<div class=\"discipleship-overdue-meta\"><span>Cabang</span><strong>" . h($branchLabel) . "</strong></div>";
                    }
                    echo "<div class=\"discipleship-overdue-meta\"><span>Batch Mulai MSK</span><strong>" . h($mskMonthLabel) . "</strong></div>";
                    echo "<div class=\"discipleship-overdue-meta\"><span>WhatsApp</span><strong>" . h($phoneLabel) . "</strong></div>";
                    echo "</div>\n";
                }
                echo "      </div>\n";
                echo "    </div>\n";
            }
            echo "  </article>\n";
            echo "  <article class=\"card member-pie-card discipleship-overdue-card is-report\">\n";
            echo "    <div class=\"discipleship-overdue-head\"><div><span class=\"discipleship-overdue-kicker\">Tindak Lanjut Jurnal Temu DG</span><h2>Belum Lapor DG 30 Hari Terakhir</h2><p>Kelompok yang belum mengirim laporan DG dalam 30 hari terakhir.</p></div><span class=\"discipleship-overdue-count\">" . h(number_format((int) count($groupsNoRecentReportRows), 0, ',', '.')) . "</span></div>\n";
            if (count($groupsNoRecentReportRows) === 0) {
                echo "    <div class=\"chart-empty-inline\">Semua kelompok sudah melaporkan pertemuan dalam 30 hari terakhir.</div>\n";
            } else {
                echo "    <div class=\"discipleship-overdue-list-wrap\">\n";
                echo "      <div class=\"discipleship-overdue-list\">\n";
                foreach ($groupsNoRecentReportRows as $row) {
                    $groupMembersFirstNames = trim((string) ($row['members_first_names_label'] ?? '-'));
                    if ($groupMembersFirstNames === '') {
                        $groupMembersFirstNames = '-';
                    }
                    $leaderName = trim((string) ($row['leader_name'] ?? '-'));
                    if ($leaderName === '') {
                        $leaderName = '-';
                    }
                    $progressLabel = trim((string) ($row['progress'] ?? 'DG 1'));
                    if ($progressLabel === '') {
                        $progressLabel = 'DG 1';
                    }
                    $lastReportLabel = trim((string) ($row['last_report_label'] ?? 'Belum Pernah Lapor'));
                    if ($lastReportLabel === '') {
                        $lastReportLabel = 'Belum Pernah Lapor';
                    }
                    echo "        <div class=\"discipleship-overdue-item\">";
                    echo "<div class=\"discipleship-overdue-top\"><span class=\"name\">" . h($leaderName) . "</span><span class=\"badge muted\">" . h($progressLabel) . "</span></div>";
                    echo "<div class=\"discipleship-overdue-meta\"><span>Peserta</span><strong>" . h($groupMembersFirstNames) . "</strong></div>";
                    echo "<div class=\"discipleship-overdue-meta\"><span>Terakhir Lapor</span><strong>" . h($lastReportLabel) . "</strong></div>";
                    echo "</div>\n";
                }
                echo "      </div>\n";
                echo "    </div>\n";
            }
            echo "  </article>\n";
            echo "</section>\n";
        }
        if (!$centralReadOnly && count($incompleteMskEditTemplates) > 0) {
            echo "<div class=\"is-hidden\" data-msk-edit-templates>\n";
            foreach ($incompleteMskEditTemplates as $templateId => $templateData) {
                $templateTitle = trim((string) ($templateData['title'] ?? 'Edit Sesi MSK'));
                if ($templateTitle === '') {
                    $templateTitle = 'Edit Sesi MSK';
                }
                $templateContent = (string) ($templateData['content'] ?? '');
                echo "<template data-msk-edit-template=\"" . h($templateId) . "\" data-msk-edit-template-title=\"" . h($templateTitle) . "\">" . $templateContent . "</template>\n";
            }
            echo "</div>\n";
            echo "<div class=\"modal\" id=\"discipleship-msk-edit-modal\" data-msk-edit-modal data-msk-edit-auto-open=\"" . h($autoOpenIncompleteMskEditId) . "\" aria-hidden=\"true\" role=\"dialog\" aria-modal=\"true\">\n";
            echo "  <div class=\"modal-card member-view-modal-card\">\n";
            echo "    <div class=\"modal-head\">\n";
            echo "      <div class=\"modal-title\" data-msk-edit-title>Edit Sesi MSK</div>\n";
            echo "      <button class=\"btn tiny ghost\" type=\"button\" data-msk-edit-close>&times;</button>\n";
            echo "    </div>\n";
            echo "    <div class=\"modal-body\" data-msk-edit-body>\n";
            echo "      <div class=\"panel-note\">Pilih tombol edit pada peserta untuk memperbarui checklist sesi MSK.</div>\n";
            echo "    </div>\n";
            echo "  </div>\n";
            echo "</div>\n";
        }
        echo "<script>\n";
        echo "(function () {\n";
        echo "  var stages = document.querySelectorAll('.member-pie-stage');\n";
        echo "  if (!stages || stages.length === 0) {\n";
        echo "    return;\n";
        echo "  }\n";
        echo "  stages.forEach(function (stage) {\n";
        echo "    var tooltip = stage.querySelector('[data-member-pie-tooltip]');\n";
        echo "    var segments = stage.querySelectorAll('.member-pie-segment');\n";
        echo "    if (!tooltip || !segments || segments.length === 0) {\n";
        echo "      return;\n";
        echo "    }\n";
        echo "    var clearActive = function () {\n";
        echo "      segments.forEach(function (segment) {\n";
        echo "        segment.classList.remove('is-active');\n";
        echo "      });\n";
        echo "    };\n";
        echo "    var positionTooltip = function (event) {\n";
        echo "      var rect = stage.getBoundingClientRect();\n";
        echo "      var x;\n";
        echo "      var y;\n";
        echo "      if (event && typeof event.clientX === 'number' && typeof event.clientY === 'number') {\n";
        echo "        x = event.clientX - rect.left + 12;\n";
        echo "        y = event.clientY - rect.top - 14;\n";
        echo "      } else {\n";
        echo "        x = (rect.width - tooltip.offsetWidth) / 2;\n";
        echo "        y = 8;\n";
        echo "      }\n";
        echo "      x = Math.max(8, Math.min(x, rect.width - tooltip.offsetWidth - 8));\n";
        echo "      y = Math.max(8, Math.min(y, rect.height - tooltip.offsetHeight - 8));\n";
        echo "      tooltip.style.left = x + 'px';\n";
        echo "      tooltip.style.top = y + 'px';\n";
        echo "    };\n";
        echo "    var showTooltip = function (segment, event) {\n";
        echo "      var tip = segment.getAttribute('data-member-pie-segment-tip') || '';\n";
        echo "      if (tip === '') {\n";
        echo "        return;\n";
        echo "      }\n";
        echo "      clearActive();\n";
        echo "      segment.classList.add('is-active');\n";
        echo "      tooltip.textContent = tip;\n";
        echo "      tooltip.classList.add('is-visible');\n";
        echo "      positionTooltip(event);\n";
        echo "    };\n";
        echo "    var hideTooltip = function () {\n";
        echo "      clearActive();\n";
        echo "      tooltip.classList.remove('is-visible');\n";
        echo "    };\n";
        echo "    segments.forEach(function (segment) {\n";
        echo "      segment.addEventListener('mouseenter', function (event) {\n";
        echo "        showTooltip(segment, event);\n";
        echo "      });\n";
        echo "      segment.addEventListener('mousemove', function (event) {\n";
        echo "        if (tooltip.classList.contains('is-visible')) {\n";
        echo "          positionTooltip(event);\n";
        echo "        }\n";
        echo "      });\n";
        echo "      segment.addEventListener('mouseleave', hideTooltip);\n";
        echo "      segment.addEventListener('focus', function () {\n";
        echo "        showTooltip(segment, null);\n";
        echo "      });\n";
        echo "      segment.addEventListener('blur', hideTooltip);\n";
        echo "    });\n";
        echo "  });\n";
        echo "})();\n";
        echo "</script>\n";

    }

    $extractFirstName = function (string $fullName): string {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $fullName);
        if (!is_array($parts) || count($parts) === 0) {
            return '';
        }
        return trim((string) $parts[0]);
    };
    $allPeopleLabelsById = [];
    foreach (($discipleshipV2Model['discipleship_persons'] ?? []) as $personRecord) {
        if (!is_array($personRecord)) {
            continue;
        }
        $personId = trim((string) ($personRecord['id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $personName = trim((string) ($personRecord['full_name'] ?? ''));
        if ($personName === '' && isset($peopleById[$personId])) {
            $personName = trim((string) ($peopleById[$personId]['name'] ?? ''));
        }
        $allPeopleLabelsById[$personId] = $personName !== '' ? $personName : '-';
    }
    foreach ($peopleById as $personId => $personRow) {
        $personId = trim((string) $personId);
        if ($personId === '') {
            continue;
        }
        if (!isset($allPeopleLabelsById[$personId])) {
            $personName = trim((string) ($personRow['name'] ?? ''));
            $allPeopleLabelsById[$personId] = $personName !== '' ? $personName : '-';
        }
    }

    $groupsSorted = $groups;
    if (!empty($discipleshipV2Model['discipleship_groups']) && is_array($discipleshipV2Model['discipleship_groups'])) {
        $groupsSorted = [];
        foreach (($discipleshipV2Model['discipleship_groups'] ?? []) as $groupRecord) {
            if (!is_array($groupRecord)) {
                continue;
            }
            $groupId = trim((string) ($groupRecord['id'] ?? ''));
            if ($groupId === '') {
                continue;
            }

            $leaderId = '';
            $assistantId = '';
            $latestLeaderSort = '';
            $latestAssistantSort = '';
            foreach (($discipleshipV2Model['group_leaderships'] ?? []) as $leadershipRecord) {
                if (!is_array($leadershipRecord)) {
                    continue;
                }
                if (trim((string) ($leadershipRecord['group_id'] ?? '')) !== $groupId) {
                    continue;
                }
                $leaderPersonId = trim((string) ($leadershipRecord['leader_person_id'] ?? ''));
                if ($leaderPersonId === '') {
                    continue;
                }
                $leadershipRole = strtolower(trim((string) ($leadershipRecord['role'] ?? 'leader')));
                $leadershipSort = trim((string) ($leadershipRecord['end_date'] ?? ''));
                if ($leadershipSort === '') {
                    $leadershipSort = trim((string) ($leadershipRecord['start_date'] ?? ''));
                }
                if ($leadershipSort === '') {
                    $leadershipSort = trim((string) ($leadershipRecord['updated_at'] ?? $leadershipRecord['created_at'] ?? ''));
                }
                if ($leadershipRole === 'co_leader' || $leadershipRole === 'assistant') {
                    if ($assistantId === '' || strcmp($leadershipSort, $latestAssistantSort) > 0) {
                        $assistantId = $leaderPersonId;
                        $latestAssistantSort = $leadershipSort;
                    }
                } else {
                    if ($leaderId === '' || strcmp($leadershipSort, $latestLeaderSort) > 0) {
                        $leaderId = $leaderPersonId;
                        $latestLeaderSort = $leadershipSort;
                    }
                }
            }

            $activeMemberIds = [];
            $historyMemberIds = [];
            $historyMemberSortById = [];
            foreach (($discipleshipV2Model['group_memberships'] ?? []) as $membershipRecord) {
                if (!is_array($membershipRecord)) {
                    continue;
                }
                if (trim((string) ($membershipRecord['group_id'] ?? '')) !== $groupId) {
                    continue;
                }
                $memberPersonId = trim((string) ($membershipRecord['person_id'] ?? ''));
                if ($memberPersonId === '') {
                    continue;
                }
                $historySort = trim((string) ($membershipRecord['end_date'] ?? ''));
                if ($historySort === '') {
                    $historySort = trim((string) ($membershipRecord['start_date'] ?? ''));
                }
                if ($historySort === '') {
                    $historySort = trim((string) ($membershipRecord['updated_at'] ?? $membershipRecord['created_at'] ?? ''));
                }
                if (!isset($historyMemberSortById[$memberPersonId]) || strcmp($historySort, (string) $historyMemberSortById[$memberPersonId]) > 0) {
                    $historyMemberSortById[$memberPersonId] = $historySort;
                }
                if (!in_array($memberPersonId, $historyMemberIds, true)) {
                    $historyMemberIds[] = $memberPersonId;
                }
                if (dgv2_is_current_period($membershipRecord) && !in_array($memberPersonId, $activeMemberIds, true)) {
                    $activeMemberIds[] = $memberPersonId;
                }
            }
            usort($historyMemberIds, static function (string $a, string $b) use ($activeMemberIds, $historyMemberSortById): int {
                $aActive = in_array($a, $activeMemberIds, true) ? 1 : 0;
                $bActive = in_array($b, $activeMemberIds, true) ? 1 : 0;
                if ($aActive !== $bActive) {
                    return $bActive <=> $aActive;
                }
                $aSort = (string) ($historyMemberSortById[$a] ?? '');
                $bSort = (string) ($historyMemberSortById[$b] ?? '');
                if ($aSort !== $bSort) {
                    return strcmp($bSort, $aSort);
                }
                return strcmp($a, $b);
            });

            $groupsSorted[] = [
                'id' => $groupId,
                'leader_id' => $leaderId,
                'assistant_id' => $assistantId,
                'name' => trim((string) ($groupRecord['name'] ?? 'Kelompok')) ?: 'Kelompok',
                'member_ids' => $activeMemberIds,
                'history_member_ids' => $historyMemberIds,
                'progress' => normalize_dg_progress_value((string) ($groupRecord['current_stage'] ?? $groupRecord['start_stage'] ?? '')) ?: 'DG 1',
                'status' => strtolower(trim((string) ($groupRecord['status'] ?? 'active'))),
                'notes' => trim((string) ($groupRecord['notes'] ?? '')),
                'created_at' => trim((string) ($groupRecord['created_at'] ?? '')),
                'updated_at' => trim((string) ($groupRecord['updated_at'] ?? '')),
            ];
        }
    }
    usort($groupsSorted, function ($a, $b) use ($peopleById) {
        $progressRank = static function (string $progress): int {
            $normalized = normalize_dg_progress_value($progress);
            if (stripos($normalized, 'DG 1') !== false) {
                return 1;
            }
            if (stripos($normalized, 'DG 2') !== false) {
                return 2;
            }
            if (stripos($normalized, 'DG 3') !== false) {
                return 3;
            }
            return 9;
        };
        $rankA = $progressRank((string) ($a['progress'] ?? ''));
        $rankB = $progressRank((string) ($b['progress'] ?? ''));
        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }
        $statusA = strtolower(trim((string) ($a['status'] ?? 'active')));
        $statusB = strtolower(trim((string) ($b['status'] ?? 'active')));
        $activeA = $statusA === 'active' ? 1 : 0;
        $activeB = $statusB === 'active' ? 1 : 0;
        if ($activeA !== $activeB) {
            return $activeB <=> $activeA;
        }
        $leaderA = person_label($peopleById, (string) ($a['leader_id'] ?? ''), '');
        $leaderB = person_label($peopleById, (string) ($b['leader_id'] ?? ''), '');
        $cmp = strcasecmp($leaderA, $leaderB);
        if ($cmp !== 0) {
            return $cmp;
        }
        $aTime = (string) ($a['created_at'] ?? '');
        $bTime = (string) ($b['created_at'] ?? '');
        if ($aTime !== $bTime) {
            return strcmp($aTime, $bTime);
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });
    $groupRowsPrepared = [];
    $groupsInDg1Count = 0;
    $groupsInDg2Count = 0;
    $groupsInDg3Count = 0;
    foreach ($groupsSorted as $grp) {
        if (!is_array($grp)) {
            continue;
        }
        $leaderId = (string) ($grp['leader_id'] ?? '');
        $assistantId = (string) ($grp['assistant_id'] ?? '');
        $leaderName = person_label($peopleById, $leaderId, '-');
        $assistantName = $assistantId !== '' ? person_label($peopleById, $assistantId, '-') : '-';
        $progressLabel = trim((string) ($grp['progress'] ?? ''));
        if ($progressLabel === '') {
            $progressLabel = '-';
        }
        $groupName = trim((string) ($grp['name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $groupStatus = strtolower(trim((string) ($grp['status'] ?? 'active')));
        $isActiveGroup = $groupStatus === 'active';
        $memberIds = $isActiveGroup ? ($grp['member_ids'] ?? []) : ($grp['history_member_ids'] ?? ($grp['member_ids'] ?? []));
        if (!is_array($memberIds)) {
            $memberIds = [];
        }
        $memberFirstNames = [];
        $memberCount = 0;
        $seenMemberIds = [];
        foreach ($memberIds as $mid) {
            $memberId = trim((string) $mid);
            if ($memberId === '' || isset($seenMemberIds[$memberId])) {
                continue;
            }
            $seenMemberIds[$memberId] = true;
            $memberName = trim((string) ($allPeopleLabelsById[$memberId] ?? ($peopleById[$memberId]['name'] ?? '')));
            if ($memberName === '') {
                continue;
            }
            $memberCount++;
            $memberFirstName = $extractFirstName($memberName);
            if ($memberFirstName === '') {
                continue;
            }
            $memberFirstNames[] = $memberFirstName;
        }
        $memberLabel = count($memberFirstNames) > 0 ? implode(', ', $memberFirstNames) : '-';
        $leaderSummary = $assistantId !== '' && $assistantName !== '-' ? 'Pendamping: ' . $assistantName : 'Tanpa pendamping';
        $progressToneClass = 'is-neutral';
        if (stripos($progressLabel, 'DG 1') !== false) {
            $progressToneClass = 'is-dg1';
            $groupsInDg1Count++;
        } elseif (stripos($progressLabel, 'DG 2') !== false) {
            $progressToneClass = 'is-dg2';
            $groupsInDg2Count++;
        } elseif (stripos($progressLabel, 'DG 3') !== false) {
            $progressToneClass = 'is-dg3';
            $groupsInDg3Count++;
        }
        $memberSummary = $memberCount > 0 ? $memberLabel : 'Belum ada peserta';
        $groupStatusBadge = $isActiveGroup
            ? '<span class="group-status-badge is-active">Aktif</span>'
            : '<span class="group-status-badge is-inactive">Nonaktif</span>';
        $progressKey = 'none';
        if (stripos($progressLabel, 'DG 1') !== false) {
            $progressKey = 'dg1';
        } elseif (stripos($progressLabel, 'DG 2') !== false) {
            $progressKey = 'dg2';
        } elseif (stripos($progressLabel, 'DG 3') !== false) {
            $progressKey = 'dg3';
        }
        $groupRowsPrepared[] = [
            'row_class' => $isActiveGroup ? 'is-group-active' : 'is-group-inactive',
            'row_status' => $isActiveGroup ? 'active' : 'inactive',
            'row_progress' => $progressKey,
            'leader_html' => "<div class=\"group-name-cell\"><div class=\"group-name-main\">" . h($leaderName) . "</div><div class=\"group-name-sub\">" . h($leaderSummary) . "</div></div>",
            'status_html' => "<div class=\"group-status-cell\">" . $groupStatusBadge . "</div>",
            'progress_html' => "<div class=\"group-progress-cell\"><span class=\"group-progress-badge " . h($progressToneClass) . "\">" . h($progressLabel) . "</span><div class=\"group-progress-sub\">" . h($memberCount > 0 ? ((string) $memberCount . ($isActiveGroup ? ' peserta aktif' : ' peserta riwayat')) : ($isActiveGroup ? 'Belum ada peserta aktif' : 'Belum ada riwayat peserta')) . "</div></div>",
            'members_html' => "<div class=\"group-members-cell\"><div class=\"group-members-main\">" . h($memberSummary) . "</div><div class=\"group-members-sub\">" . h($memberCount > 0 ? ($isActiveGroup ? 'Nama depan peserta aktif dalam kelompok' : 'Riwayat nama depan peserta kelompok') : ($isActiveGroup ? 'Tambahkan peserta dari pohon DG' : 'Belum ada riwayat anggota')) . "</div></div>",
            'search_text' => strtolower($leaderName . ' ' . $assistantName . ' ' . $progressLabel . ' ' . $memberLabel . ' ' . ($isActiveGroup ? 'aktif' : 'nonaktif')),
        ];
    }
    $totalGroupRows = count($groupRowsPrepared);

    if ($showGroupsTable) {
        echo "<section class=\"card groups-hero-card\">\n";
        echo "  <div class=\"groups-hero-head\">\n";
        echo "    <div class=\"groups-hero-copy\">\n";
        echo "      <div class=\"groups-hero-kicker\">Kelompok DG</div>\n";
        echo "      <h1>Daftar Kelompok DG</h1>\n";
        echo "      <p>Lihat leader, progres, dan komposisi peserta aktif dalam setiap Kelompok DG secara ringkas.</p>\n";
        echo "    </div>\n";
        echo "    <div class=\"groups-hero-stats\">\n";
        echo "      <div class=\"groups-hero-stat\"><span class=\"groups-hero-stat-label\">Kelompok DG</span><strong class=\"groups-hero-stat-value\" data-groups-stat=\"total\">" . h((string) $totalGroupRows) . "</strong></div>\n";
        echo "      <div class=\"groups-hero-stat\"><span class=\"groups-hero-stat-label\">DG 1</span><strong class=\"groups-hero-stat-value\" data-groups-stat=\"dg1\">" . h((string) ($groupsInDg1Count + 0)) . "</strong></div>\n";
        echo "      <div class=\"groups-hero-stat\"><span class=\"groups-hero-stat-label\">DG 2</span><strong class=\"groups-hero-stat-value\" data-groups-stat=\"dg2\">" . h((string) ($groupsInDg2Count + 0)) . "</strong></div>\n";
        echo "      <div class=\"groups-hero-stat\"><span class=\"groups-hero-stat-label\">DG 3</span><strong class=\"groups-hero-stat-value\" data-groups-stat=\"dg3\">" . h((string) ($groupsInDg3Count + 0)) . "</strong></div>\n";
        echo "    </div>\n";
        echo "  </div>\n";
        echo "  <div class=\"actions groups-hero-tools\">\n";
        echo "    <div class=\"groups-hero-filter-wrap\">\n";
        echo "      <select class=\"search groups-status-filter\" data-filter=\"groups-dashboard-table\" data-filter-role=\"status\" aria-label=\"Filter status kelompok DG\">\n";
        echo "        <option value=\"all\">Semua Kelompok</option>\n";
        echo "        <option value=\"active\">Kelompok Aktif</option>\n";
        echo "        <option value=\"inactive\">Kelompok Tidak Aktif</option>\n";
        echo "      </select>\n";
        echo "    </div>\n";
        echo "    <div class=\"groups-hero-search-wrap\">\n";
    render_table_search_input('groups-dashboard-table', 'Cari leader, pendamping, progres, atau peserta...', 'search groups-table-search', 'Cari Kelompok DG', '      ');
        echo "    </div>\n";
        echo "  </div>\n";
        echo "</section>\n";

        echo "<section class=\"card discipleship-list-card table-card-plain\" id=\"discipleship-groups-list\">\n";
        echo "  <div class=\"table-wrap\">\n";
        echo "    <table class=\"table groups-dashboard-table\" id=\"groups-dashboard-table\">\n";
        echo "      <thead><tr><th>Leader & Pendamping</th><th>Status</th><th>Progress</th><th>Anggota</th></tr></thead>\n";
        echo "      <tbody>\n";
        foreach ($groupRowsPrepared as $row) {
            $rowClass = trim((string) ($row['row_class'] ?? ''));
            $rowStatus = trim((string) ($row['row_status'] ?? 'active'));
            $rowProgress = trim((string) ($row['row_progress'] ?? 'none'));
            echo "<tr" . ($rowClass !== '' ? " class=\"" . h($rowClass) . "\"" : '') . " data-group-status=\"" . h($rowStatus) . "\" data-group-progress=\"" . h($rowProgress) . "\">";
            echo "<td>" . (string) ($row['leader_html'] ?? '-') . "</td>";
            echo "<td>" . (string) ($row['status_html'] ?? '-') . "</td>";
            echo "<td>" . (string) ($row['progress_html'] ?? '-') . "</td>";
            echo "<td>" . (string) ($row['members_html'] ?? '-') . "</td>";
            echo "</tr>\n";
        }
        if ($totalGroupRows === 0) {
            echo "<tr><td colspan=\"4\">Belum ada kelompok.</td></tr>\n";
        }
        echo "      </tbody>\n";
        echo "    </table>\n";
        echo "  </div>\n";
        echo "</section>\n";
    }

    $peopleSorted = $people;
    $childrenMap = [];
    foreach ($people as $p) {
        $parentIds = get_parent_ids($p);
        $primaryParent = $parentIds[0] ?? '';
        if ($primaryParent !== '') {
            $childrenMap[$primaryParent][] = $p;
        }
    }
    $dgStageRank = static function (string $stage): int {
        $stage = normalize_dg_progress_value($stage);
        if ($stage === 'DG 3') {
            return 3;
        }
        if ($stage === 'DG 2') {
            return 2;
        }
        if ($stage === 'DG 1') {
            return 1;
        }
        return 0;
    };
    $peopleLastProgressMap = [];
    foreach (($discipleshipV2Model['group_memberships'] ?? []) as $membershipRow) {
        if (!is_array($membershipRow)) {
            continue;
        }
        $personId = trim((string) ($membershipRow['person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $stage = normalize_dg_progress_value((string) ($membershipRow['stage'] ?? ''));
        if ($stage === '') {
            continue;
        }
        $sortDate = trim((string) ($membershipRow['end_date'] ?? ''));
        if ($sortDate === '') {
            $sortDate = trim((string) ($membershipRow['start_date'] ?? ''));
        }
        if ($sortDate === '') {
            $sortDate = trim((string) ($membershipRow['updated_at'] ?? $membershipRow['created_at'] ?? ''));
        }
        $existing = $peopleLastProgressMap[$personId] ?? null;
        if (!is_array($existing)) {
            $peopleLastProgressMap[$personId] = [
                'stage' => $stage,
                'sort_date' => $sortDate,
                'stage_rank' => $dgStageRank($stage),
            ];
            continue;
        }
        $existingSortDate = trim((string) ($existing['sort_date'] ?? ''));
        $replaceExisting = false;
        if ($sortDate !== '' && ($existingSortDate === '' || strcmp($sortDate, $existingSortDate) > 0)) {
            $replaceExisting = true;
        } elseif ($sortDate === $existingSortDate && $dgStageRank($stage) > (int) ($existing['stage_rank'] ?? 0)) {
            $replaceExisting = true;
        }
        if ($replaceExisting) {
            $peopleLastProgressMap[$personId] = [
                'stage' => $stage,
                'sort_date' => $sortDate,
                'stage_rank' => $dgStageRank($stage),
            ];
        }
    }
    $peopleCurrentProgressMap = [];
    foreach ($groups as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $progressLabel = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        if ($progressLabel === '') {
            $progressLabel = '-';
        }

        $memberIds = $groupRow['member_ids'] ?? [];
        if (is_array($memberIds)) {
            foreach ($memberIds as $memberIdRaw) {
                $memberId = trim((string) $memberIdRaw);
                if ($memberId !== '') {
                    if (!isset($peopleCurrentProgressMap[$memberId])) {
                        $peopleCurrentProgressMap[$memberId] = [];
                    }
                    if (!in_array($progressLabel, $peopleCurrentProgressMap[$memberId], true)) {
                        $peopleCurrentProgressMap[$memberId][] = $progressLabel;
                    }
                }
            }
        }
    }
    $peopleCompletedDgFilterMap = [
        'dg1' => [],
        'dg2' => [],
        'dg3' => [],
    ];
    $completionReasonValues = ['continued_to_child_group', 'group_completed', 'stage_transition'];
    foreach (($discipleshipV2Model['group_memberships'] ?? []) as $membershipRecord) {
        if (!is_array($membershipRecord)) {
            continue;
        }
        $personId = trim((string) ($membershipRecord['person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $stage = normalize_dg_progress_value((string) ($membershipRecord['stage'] ?? ''));
        if ($stage === '') {
            continue;
        }
        $stageRank = $dgStageRank($stage);
        $reasonEnd = trim((string) ($membershipRecord['reason_end'] ?? ''));
        if ($stageRank >= 2 || ($stage === 'DG 1' && in_array($reasonEnd, $completionReasonValues, true))) {
            $peopleCompletedDgFilterMap['dg1'][$personId] = true;
        }
        if ($stageRank >= 3 || ($stage === 'DG 2' && in_array($reasonEnd, $completionReasonValues, true))) {
            $peopleCompletedDgFilterMap['dg2'][$personId] = true;
        }
        if ($stage === 'DG 3' && in_array($reasonEnd, $completionReasonValues, true)) {
            $peopleCompletedDgFilterMap['dg3'][$personId] = true;
        }
    }
    $peopleBridgeFilterMap = [];
    $peopleByMemberIdForFilter = [];
    $peopleByNameForFilter = [];
    foreach ($people as $personRow) {
        if (!is_array($personRow)) {
            continue;
        }
        $personId = trim((string) ($personRow['id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $personMemberId = trim((string) ($personRow['member_id'] ?? ''));
        if ($personMemberId !== '' && !isset($peopleByMemberIdForFilter[$personMemberId])) {
            $peopleByMemberIdForFilter[$personMemberId] = $personId;
        }
        $personNameKey = strtolower(trim((string) ($personRow['name'] ?? '')));
        if ($personNameKey !== '' && !isset($peopleByNameForFilter[$personNameKey])) {
            $peopleByNameForFilter[$personNameKey] = $personId;
        }
    }
    foreach ($mskClasses as $participantRow) {
        if (!is_array($participantRow)) {
            continue;
        }
        $journeyBridgeStatus = normalize_journey_bridge_status((string) ($participantRow['journey_bridge_status'] ?? 'belum'));
        if (!in_array($journeyBridgeStatus, ['sudah_rg', 'sudah_kgap', 'ikut_keduanya'], true)) {
            continue;
        }
        $participantMemberId = trim((string) ($participantRow['member_id'] ?? ''));
        $resolvedPersonId = '';
        if ($participantMemberId !== '' && isset($peopleByMemberIdForFilter[$participantMemberId])) {
            $resolvedPersonId = (string) $peopleByMemberIdForFilter[$participantMemberId];
        } else {
            $participantNameKey = strtolower(trim((string) ($participantRow['full_name'] ?? '')));
            if ($participantNameKey !== '' && isset($peopleByNameForFilter[$participantNameKey])) {
                $resolvedPersonId = (string) $peopleByNameForFilter[$participantNameKey];
            }
        }
        if ($resolvedPersonId === '') {
            continue;
        }
        if (!isset($peopleBridgeFilterMap[$resolvedPersonId])) {
            $peopleBridgeFilterMap[$resolvedPersonId] = [];
        }
        if (in_array($journeyBridgeStatus, ['sudah_kgap', 'ikut_keduanya'], true)) {
            $peopleBridgeFilterMap[$resolvedPersonId]['kgap_complete'] = true;
        }
        if (in_array($journeyBridgeStatus, ['sudah_rg', 'ikut_keduanya'], true)) {
            $peopleBridgeFilterMap[$resolvedPersonId]['rg_complete'] = true;
        }
    }
    usort($peopleSorted, function ($a, $b) use ($peopleLastProgressMap, $dgStageRank) {
        $progressRank = static function (array $person, array $progressMap, Closure $stageRanker): int {
            $personId = trim((string) ($person['id'] ?? ''));
            $stage = $personId !== '' ? trim((string) (($progressMap[$personId]['stage'] ?? ''))) : '';
            if ($stage !== '') {
                return $stageRanker($stage);
            }
            return 9;
        };
        $rankA = $progressRank($a, $peopleLastProgressMap, $dgStageRank);
        $rankB = $progressRank($b, $peopleLastProgressMap, $dgStageRank);
        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    $peopleRowsPrepared = [];
    $peopleInDg1Count = 0;
    $peopleInDg2Count = 0;
    $peopleInDg3Count = 0;
    $peopleEverLedGroupMap = [];
    foreach (($discipleshipV2Model['group_leaderships'] ?? []) as $leadershipRow) {
        if (!is_array($leadershipRow)) {
            continue;
        }
        $leaderPersonId = trim((string) ($leadershipRow['leader_person_id'] ?? ''));
        if ($leaderPersonId === '') {
            continue;
        }
        $peopleEverLedGroupMap[$leaderPersonId] = true;
    }
    foreach ($peopleSorted as $p) {
        if (!is_array($p)) {
            continue;
        }
        $pid = (string) ($p['id'] ?? '');
        $name = trim((string) ($p['name'] ?? ''));
        if ($name === '') {
            $name = '-';
        }
        $personMemberId = trim((string) ($p['member_id'] ?? ''));
        $parentNames = format_parent_names($peopleById, get_parent_ids($p));
        $role = trim((string) ($p['role'] ?? ''));
        if (isset($peopleEverLedGroupMap[$pid])) {
            $role = 'Pemimpin';
        }
        $phone = trim((string) ($p['phone'] ?? ''));
        $childCount = count($childrenMap[$pid] ?? []);
        $lastProgressStage = trim((string) (($peopleLastProgressMap[$pid]['stage'] ?? '')));
        $currentProgressValues = $peopleCurrentProgressMap[$pid] ?? [];
        if (!is_array($currentProgressValues)) {
            $currentProgressValues = [];
        }
        $currentProgressValues = array_values(array_filter(array_map('strval', $currentProgressValues), function ($value): bool {
            return trim($value) !== '';
        }));
        $progressLabel = $lastProgressStage !== '' ? $lastProgressStage : '-';
        $parentSummary = $parentNames !== '' ? $parentNames : 'Belum terhubung ke pembina';
        $roleLabel = $role !== '' ? $role : 'Anggota';
        $roleToneClass = 'is-member';
        $roleLabelLower = strtolower($roleLabel);
        if (strpos($roleLabelLower, 'leader') !== false || strpos($roleLabelLower, 'pemimpin') !== false) {
            $roleToneClass = 'is-leader';
        } elseif (strpos($roleLabelLower, 'coach') !== false || strpos($roleLabelLower, 'mentor') !== false) {
            $roleToneClass = 'is-coach';
        }
        $progressBadges = [];
        $progressFilterState = 'none';
        $progressFilterTokens = [];
        if ($lastProgressStage !== '') {
            $progressToneClass = 'is-neutral';
            $isCurrentStageActive = in_array($lastProgressStage, $currentProgressValues, true);
            $progressFilterState = $isCurrentStageActive ? 'active' : 'complete';
            $progressFilterTokens[] = $progressFilterState;
            $progressBadgeText = $isCurrentStageActive
                ? ('Sedang ' . $lastProgressStage)
                : ($lastProgressStage . ' Selesai');
            if (stripos($lastProgressStage, 'DG 1') !== false) {
                $progressToneClass = $isCurrentStageActive ? 'is-dg1-active' : 'is-dg1-complete';
            } elseif (stripos($lastProgressStage, 'DG 2') !== false) {
                $progressToneClass = $isCurrentStageActive ? 'is-dg2-active' : 'is-dg2-complete';
            } elseif (stripos($lastProgressStage, 'DG 3') !== false) {
                $progressToneClass = $isCurrentStageActive ? 'is-dg3-active' : 'is-dg3-complete';
            }
            $progressBadges[] = "<span class=\"people-progress-badge " . h($progressToneClass) . "\">" . h($progressBadgeText) . "</span>";
        }
        if (count($progressBadges) === 0) {
            $isExternalFallback = $personMemberId === ''
                || isset($peopleEverLedGroupMap[$pid])
                || $childCount > 0;
            $fallbackProgressLabel = $isExternalFallback ? 'External' : 'Belum masuk progres';
            if ($isExternalFallback) {
                $progressFilterState = 'external';
                $progressFilterTokens[] = 'external';
            }
            $progressBadges[] = "<span class=\"people-progress-badge is-neutral\">" . h($fallbackProgressLabel) . "</span>";
        }
        foreach ($currentProgressValues as $progressValue) {
            if (stripos($progressValue, 'DG 1') !== false) {
                $progressFilterTokens[] = 'active_dg1';
            }
            if (stripos($progressValue, 'DG 2') !== false) {
                $progressFilterTokens[] = 'active_dg2';
            }
            if (stripos($progressValue, 'DG 3') !== false) {
                $progressFilterTokens[] = 'active_dg3';
            }
        }
        if (!empty($peopleCompletedDgFilterMap['dg1'][$pid])) {
            $progressFilterTokens[] = 'complete_dg1';
        }
        if (!empty($peopleCompletedDgFilterMap['dg2'][$pid])) {
            $progressFilterTokens[] = 'complete_dg2';
        }
        if (!empty($peopleCompletedDgFilterMap['dg3'][$pid])) {
            $progressFilterTokens[] = 'complete_dg3';
        }
        foreach (($peopleBridgeFilterMap[$pid] ?? []) as $bridgeFilterKey => $hasBridgeStatus) {
            if ($hasBridgeStatus) {
                $progressFilterTokens[] = (string) $bridgeFilterKey;
            }
        }
        $progressFilterTokens = array_values(array_unique(array_filter($progressFilterTokens, static function ($token): bool {
            return trim((string) $token) !== '';
        })));
        if (count($progressFilterTokens) === 0) {
            $progressFilterTokens[] = $progressFilterState;
        }
        $lastProgressKey = 'none';
        if (stripos($lastProgressStage, 'DG 1') !== false) {
            $lastProgressKey = 'dg1';
        } elseif (stripos($lastProgressStage, 'DG 2') !== false) {
            $lastProgressKey = 'dg2';
        } elseif (stripos($lastProgressStage, 'DG 3') !== false) {
            $lastProgressKey = 'dg3';
        }
        $phoneDigits = normalize_whatsapp_digits($phone);
        $phoneLabel = $phone !== '' ? $phone : 'Belum ada nomor';
        $phoneHtml = "<span class=\"people-contact-empty\">Belum ada nomor</span>";
        if ($phone !== '') {
            $phoneHtml = $phoneDigits !== ''
                ? "<a class=\"people-contact-link\" href=\"" . h('https://wa.me/' . $phoneDigits) . "\" target=\"_blank\" rel=\"noopener\">" . h($phoneLabel) . "</a>"
                : "<span class=\"people-contact-empty\">" . h($phoneLabel) . "</span>";
        }
        if ($lastProgressKey === 'dg1') {
            $peopleInDg1Count++;
        }
        if ($lastProgressKey === 'dg2') {
            $peopleInDg2Count++;
        }
        if ($lastProgressKey === 'dg3') {
            $peopleInDg3Count++;
        }
        $peopleRowsPrepared[] = [
            'row_filter_state' => implode(' ', $progressFilterTokens),
            'row_progress_key' => $lastProgressKey,
            'name_html' => "<div class=\"people-name-cell\"><div class=\"people-name-main\">" . h($name) . "</div><div class=\"people-name-sub\">" . h($parentSummary) . "</div></div>",
            'role_html' => "<div class=\"people-role-cell\"><span class=\"people-role-badge " . h($roleToneClass) . "\">" . h($roleLabel) . "</span><div class=\"people-role-sub\">" . h($childCount > 0 ? (string) $childCount . ' binaan langsung' : 'Belum punya binaan langsung') . "</div></div>",
            'progress_html' => "<div class=\"people-progress-cell\">" . implode('', $progressBadges) . "</div>",
            'phone_html' => "<div class=\"people-contact-cell\">" . $phoneHtml . "</div>",
            'child_html' => "<div class=\"people-child-count\"><strong>" . h((string) $childCount) . "</strong><span>" . h($childCount === 1 ? 'peserta' : 'peserta') . "</span></div>",
            'search_text' => strtolower($name . ' ' . $parentNames . ' ' . $role . ' ' . $progressLabel . ' ' . $phone . ' ' . (string) $childCount),
        ];
    }
    $totalPeopleRows = count($peopleRowsPrepared);

    if ($showPeopleTable) {
        echo "<section class=\"card people-hero-card\">\n";
        echo "  <div class=\"people-hero-head\">\n";
        echo "    <div class=\"people-hero-copy\">\n";
        echo "      <div class=\"people-hero-kicker\">ANGGOTA DG</div>\n";
        echo "      <h1>Daftar Anggota DG</h1>\n";
        echo "      <p>Pantau relasi pembinaan, progres DG, dan kontak anggota yang sedang berjalan di alur DG.</p>\n";
        echo "    </div>\n";
        echo "    <div class=\"people-hero-stats\">\n";
        echo "      <div class=\"people-hero-stat\"><span class=\"people-hero-stat-label\">Peserta DG</span><strong class=\"people-hero-stat-value\" data-people-stat=\"total\">" . h((string) $totalPeopleRows) . "</strong></div>\n";
        echo "      <div class=\"people-hero-stat\"><span class=\"people-hero-stat-label\">DG1</span><strong class=\"people-hero-stat-value\" data-people-stat=\"dg1\">" . h((string) $peopleInDg1Count) . "</strong></div>\n";
        echo "      <div class=\"people-hero-stat\"><span class=\"people-hero-stat-label\">DG2</span><strong class=\"people-hero-stat-value\" data-people-stat=\"dg2\">" . h((string) $peopleInDg2Count) . "</strong></div>\n";
        echo "      <div class=\"people-hero-stat\"><span class=\"people-hero-stat-label\">DG3</span><strong class=\"people-hero-stat-value\" data-people-stat=\"dg3\">" . h((string) $peopleInDg3Count) . "</strong></div>\n";
        echo "    </div>\n";
        echo "  </div>\n";
        echo "  <div class=\"actions people-hero-tools\">\n";
        echo "    <div class=\"people-hero-filter-wrap\">\n";
        echo "      <select class=\"search people-status-filter\" data-filter=\"people-dashboard-table\" data-filter-role=\"people-status\" aria-label=\"Filter status progress anggota DG\">\n";
        echo "        <option value=\"all\">Semua Peserta</option>\n";
        echo "        <option value=\"active_dg1\">Sedang DG 1</option>\n";
        echo "        <option value=\"complete_dg1\">Selesai DG 1</option>\n";
        echo "        <option value=\"active_dg2\">Sedang DG 2</option>\n";
        echo "        <option value=\"complete_dg2\">Selesai DG 2</option>\n";
        echo "        <option value=\"active_dg3\">Sedang DG 3</option>\n";
        echo "        <option value=\"complete_dg3\">Selesai DG 3</option>\n";
        echo "        <option value=\"kgap_complete\">Selesai Camp GAP</option>\n";
        echo "        <option value=\"rg_complete\">Selesai RG</option>\n";
        echo "      </select>\n";
        echo "    </div>\n";
        echo "    <div class=\"people-hero-search-wrap\">\n";
    render_table_search_input('people-dashboard-table', 'Cari peserta, pembina, progres, atau kontak...', 'search people-table-search', 'Cari daftar peserta DG', '      ');
        echo "    </div>\n";
        echo "  </div>\n";
        echo "</section>\n";

        echo "<section class=\"card discipleship-list-card table-card-plain\" id=\"discipleship-people-list\">\n";
        echo "  <div class=\"table-wrap\">\n";
        echo "    <table class=\"table people-dashboard-table\" id=\"people-dashboard-table\">\n";
        echo "      <thead><tr><th>Nama & Relasi</th><th>Peran</th><th>Progress DG</th><th>Kontak</th><th>Jumlah Binaan</th></tr></thead>\n";
        echo "      <tbody>\n";
        foreach ($peopleRowsPrepared as $row) {
            $rowFilterState = trim((string) ($row['row_filter_state'] ?? 'none'));
            $rowProgressKey = trim((string) ($row['row_progress_key'] ?? 'none'));
            echo "<tr data-people-filter=\"" . h($rowFilterState) . "\" data-people-progress=\"" . h($rowProgressKey) . "\">";
            echo "<td>" . (string) ($row['name_html'] ?? '-') . "</td>";
            echo "<td>" . (string) ($row['role_html'] ?? '-') . "</td>";
            echo "<td>" . (string) ($row['progress_html'] ?? '-') . "</td>";
            echo "<td>" . (string) ($row['phone_html'] ?? '-') . "</td>";
            echo "<td>" . (string) ($row['child_html'] ?? '-') . "</td>";
            echo "</tr>\n";
        }
        if ($totalPeopleRows === 0) {
            echo "<tr><td colspan=\"5\">Belum ada data orang.</td></tr>\n";
        }
        echo "      </tbody>\n";
        echo "    </table>\n";
        echo "  </div>\n";
        echo "</section>\n";
    }

    page_footer();
    legacy_exit();
}
