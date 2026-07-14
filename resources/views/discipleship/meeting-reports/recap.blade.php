<?php

if ($page === 'dg_reports_recap') {
    $renderAsTabPanel = (bool) ($renderAsTabPanel ?? false);
    if (! $renderAsTabPanel) {
        page_header('Rekap Jurnal Temu DG', $settings, $page, false, 'page-discipleship-table-scroll');
    } else {
        echo '<section class="discipleship-tab-panel discipleship-workspace__panel discipleship-journal-panel dg-meeting-recap-panel" id="discipleship-tabpanel-meeting" role="tabpanel" aria-labelledby="discipleship-tab-meeting" tabindex="0" data-discipleship-tab-panel data-tab-key="meeting" data-page-title="Jurnal Temu DG" data-body-class="page-dg_reports_recap">'."\n";
    }
    $peopleById = index_by_id($people);
    $normalizeProgressLabel = function (string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^DG\s*([1-3])$/i', $value, $match) === 1) {
            return 'DG '.$match[1];
        }
        if (preg_match('/^[1-3]$/', $value) === 1) {
            return 'DG '.$value;
        }

        return $value;
    };
    $extractFirstName = function (string $fullName): string {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $fullName);
        if (! is_array($parts) || count($parts) === 0) {
            return '';
        }

        return trim((string) $parts[0]);
    };
    $currentGroupProgressById = [];
    foreach ($groups as $groupRow) {
        if (! is_array($groupRow)) {
            continue;
        }
        $groupId = trim((string) ($groupRow['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $groupProgress = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        if ($groupProgress === '') {
            $groupProgress = 'DG 1';
        }
        $currentGroupProgressById[$groupId] = $groupProgress;
    }

    $reportsPrepared = [];
    foreach ($dgMeetingReports as $index => $report) {
        if (! is_array($report)) {
            continue;
        }

        $reportId = trim((string) ($report['id'] ?? ''));
        if ($reportId === '') {
            $reportId = 'dg_report_row_'.(string) $index;
        }

        $leaderId = trim((string) ($report['leader_id'] ?? ''));
        $leaderName = trim((string) ($report['leader_name'] ?? ''));
        if ($leaderName === '') {
            $leaderName = '-';
        }

        $groupId = trim((string) ($report['group_id'] ?? ''));
        $groupName = trim((string) ($report['group_name'] ?? ''));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $groupProgress = normalize_dg_progress_value((string) ($report['group_progress'] ?? ''));
        if ($groupProgress === '') {
            $groupProgress = 'DG 1';
        }
        $currentGroupProgress = $groupId !== '' ? trim((string) ($currentGroupProgressById[$groupId] ?? '')) : '';
        if ($groupId !== '' && $groupProgress !== '' && $currentGroupProgress !== '' && $groupProgress !== $currentGroupProgress) {
            // Hide report from recap when group has moved to a different DG progress.
            continue;
        }

        $meetingDate = normalize_ymd_date((string) ($report['meeting_date'] ?? ''));
        $materialTopic = trim((string) ($report['material_topic'] ?? ''));
        if ($materialTopic === '') {
            $materialTopic = '-';
        }
        $absenceReason = trim((string) ($report['absence_reason'] ?? ''));
        $additionalNotes = trim((string) ($report['additional_notes'] ?? ''));

        $absentMemberNames = [];
        $rawAbsentMemberNames = $report['absent_member_names'] ?? [];
        if (is_array($rawAbsentMemberNames)) {
            foreach ($rawAbsentMemberNames as $absentName) {
                $absentName = trim((string) $absentName);
                if ($absentName === '' || in_array($absentName, $absentMemberNames, true)) {
                    continue;
                }
                $absentMemberNames[] = $absentName;
            }
        }
        $rawAbsentMemberIds = $report['absent_member_ids'] ?? [];
        if (! is_array($rawAbsentMemberIds)) {
            $rawAbsentMemberIds = [];
        }
        $absentMemberCount = count($absentMemberNames);
        if ($absentMemberCount === 0) {
            $absentMemberCount = count($rawAbsentMemberIds);
        }

        $meditationSharerNames = [];
        $rawMeditationSharerNames = $report['meditation_sharer_names'] ?? [];
        if (is_array($rawMeditationSharerNames)) {
            foreach ($rawMeditationSharerNames as $sharerName) {
                $sharerName = trim((string) $sharerName);
                if ($sharerName === '' || in_array($sharerName, $meditationSharerNames, true)) {
                    continue;
                }
                $meditationSharerNames[] = $sharerName;
            }
        }

        $qualityPrepare = parse_bool_value($report['quality_prepare'] ?? false);
        $qualityPray = parse_bool_value($report['quality_pray'] ?? false);
        $qualityShareMeditation = parse_bool_value($report['quality_share_meditation'] ?? false);
        $qualityRelational = parse_bool_value($report['quality_relational'] ?? false);
        $qualityScore = 0;
        if ($qualityPrepare) {
            $qualityScore++;
        }
        if ($qualityPray) {
            $qualityScore++;
        }
        if ($qualityShareMeditation) {
            $qualityScore++;
        }
        if ($qualityRelational) {
            $qualityScore++;
        }

        $sharingOpenness = null;
        $sharingRaw = $report['sharing_openness'] ?? null;
        if (is_numeric($sharingRaw)) {
            $sharingValue = (int) $sharingRaw;
            if ($sharingValue >= 1 && $sharingValue <= 10) {
                $sharingOpenness = $sharingValue;
            }
        }

        $meditationMinTimes = dg_progress_min_share_times($groupProgress);
        $meditationMinRaw = $report['meditation_min_times'] ?? null;
        if (is_numeric($meditationMinRaw)) {
            $meditationMinCandidate = (int) $meditationMinRaw;
            if ($meditationMinCandidate > 0) {
                $meditationMinTimes = $meditationMinCandidate;
            }
        }

        $meetingPhotos = [];
        $rawMeetingPhotos = $report['meeting_photos'] ?? [];
        if (is_array($rawMeetingPhotos)) {
            $seenPhotoPaths = [];
            foreach ($rawMeetingPhotos as $photoIndex => $photoItem) {
                $photoPath = '';
                $photoName = '';
                if (is_array($photoItem)) {
                    $photoPath = (string) ($photoItem['path'] ?? '');
                    $photoName = (string) ($photoItem['name'] ?? '');
                } elseif (is_string($photoItem)) {
                    $photoPath = $photoItem;
                }
                $safePhotoPath = sanitize_relative_upload_path($photoPath);
                if ($safePhotoPath === '' || isset($seenPhotoPaths[$safePhotoPath])) {
                    continue;
                }
                $safePhotoName = trim($photoName);
                if ($safePhotoName === '') {
                    $safePhotoName = 'Foto '.(string) ($photoIndex + 1);
                }
                $meetingPhotos[] = [
                    'path' => $safePhotoPath,
                    'name' => $safePhotoName,
                ];
                $seenPhotoPaths[$safePhotoPath] = true;
            }
        }

        $reportsPrepared[] = [
            'id' => $reportId,
            'leader_id' => $leaderId,
            'leader_name' => $leaderName,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'group_progress' => $groupProgress,
            'meeting_date' => $meetingDate,
            'material_topic' => $materialTopic,
            'absent_member_names' => $absentMemberNames,
            'absent_member_count' => $absentMemberCount,
            'absence_reason' => $absenceReason,
            'quality_prepare' => $qualityPrepare,
            'quality_pray' => $qualityPray,
            'quality_share_meditation' => $qualityShareMeditation,
            'quality_relational' => $qualityRelational,
            'quality_score' => $qualityScore,
            'sharing_openness' => $sharingOpenness,
            'meditation_sharer_names' => $meditationSharerNames,
            'meditation_min_times' => $meditationMinTimes,
            'additional_notes' => $additionalNotes,
            'meeting_photos' => $meetingPhotos,
            'created_at' => (string) ($report['created_at'] ?? ''),
        ];
    }

    usort($reportsPrepared, function ($a, $b) {
        $aMeetingDate = (string) ($a['meeting_date'] ?? '');
        $bMeetingDate = (string) ($b['meeting_date'] ?? '');
        if ($aMeetingDate !== $bMeetingDate) {
            return strcmp($bMeetingDate, $aMeetingDate);
        }
        $aCreatedAt = (string) ($a['created_at'] ?? '');
        $bCreatedAt = (string) ($b['created_at'] ?? '');
        if ($aCreatedAt !== $bCreatedAt) {
            return strcmp($bCreatedAt, $aCreatedAt);
        }

        return strcmp((string) ($b['id'] ?? ''), (string) ($a['id'] ?? ''));
    });

    $leaderSummaries = [];
    $groupSummaries = [];
    foreach ($groups as $groupRow) {
        if (! is_array($groupRow)) {
            continue;
        }
        $groupId = trim((string) ($groupRow['id'] ?? ''));
        $groupName = trim((string) ($groupRow['name'] ?? ''));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $leaderId = trim((string) ($groupRow['leader_id'] ?? ''));
        $leaderName = person_label($peopleById, $leaderId, '-');
        $leaderKey = $leaderId !== '' ? $leaderId : strtolower($leaderName);
        if ($leaderKey === '') {
            $leaderKey = 'leader_unknown';
        }
        if (! isset($leaderSummaries[$leaderKey])) {
            $leaderSummaries[$leaderKey] = [
                'name' => $leaderName,
                'report_count' => 0,
                'group_keys' => [],
                'member_keys' => [],
                'sharing_total' => 0,
                'sharing_count' => 0,
                'absent_total' => 0,
                'quality_points' => 0,
                'quality_max' => 0,
            ];
        } elseif ((string) ($leaderSummaries[$leaderKey]['name'] ?? '') === '-' && $leaderName !== '-') {
            $leaderSummaries[$leaderKey]['name'] = $leaderName;
        }

        $groupKey = $groupId !== '' ? $groupId : strtolower($groupName.'|'.$leaderKey);
        if ($groupKey === '') {
            $groupKey = 'group_unknown_'.$leaderKey;
        }
        $leaderSummaries[$leaderKey]['group_keys'][$groupKey] = true;
        $groupMemberIds = $groupRow['member_ids'] ?? [];
        $groupMemberFirstNames = [];
        if (is_array($groupMemberIds)) {
            $seenMemberIds = [];
            foreach ($groupMemberIds as $memberIdRaw) {
                $memberId = trim((string) $memberIdRaw);
                if ($memberId === '' || isset($seenMemberIds[$memberId])) {
                    continue;
                }
                $seenMemberIds[$memberId] = true;
                $leaderSummaries[$leaderKey]['member_keys'][$memberId] = true;
                $memberName = trim(person_label($peopleById, $memberId, ''));
                if ($memberName === '' || $memberName === '-') {
                    continue;
                }
                $memberFirstName = $extractFirstName($memberName);
                if ($memberFirstName === '') {
                    continue;
                }
                $groupMemberFirstNames[] = $memberFirstName;
            }
        }
        $groupMembersLabel = count($groupMemberFirstNames) > 0 ? implode(', ', $groupMemberFirstNames) : 'Belum ada anggota';

        $progressLabel = $normalizeProgressLabel((string) ($groupRow['progress'] ?? ''));
        if (! isset($groupSummaries[$groupKey])) {
            $groupSummaries[$groupKey] = [
                'group_key' => $groupKey,
                'group_id' => $groupId,
                'name' => $groupName,
                'leader_id' => $leaderId,
                'members_label' => $groupMembersLabel,
                'leader_name' => $leaderName,
                'progress' => $progressLabel,
                'report_count' => 0,
                'sharing_total' => 0,
                'sharing_count' => 0,
                'absent_total' => 0,
                'last_meeting_date' => '',
                'last_material_topic' => '',
                'last_report_created_at' => '',
            ];
        } elseif ((string) ($groupSummaries[$groupKey]['progress'] ?? '') === '' && $progressLabel !== '') {
            $groupSummaries[$groupKey]['progress'] = $progressLabel;
        }
    }

    $totalAbsentMembers = 0;
    foreach ($reportsPrepared as $row) {
        $leaderId = trim((string) ($row['leader_id'] ?? ''));
        $leaderName = trim((string) ($row['leader_name'] ?? ''));
        if ($leaderName === '') {
            $leaderName = '-';
        }
        $leaderKey = $leaderId !== '' ? $leaderId : strtolower($leaderName);
        if ($leaderKey === '') {
            $leaderKey = 'leader_unknown';
        }

        if (! isset($leaderSummaries[$leaderKey])) {
            $leaderSummaries[$leaderKey] = [
                'name' => $leaderName,
                'report_count' => 0,
                'group_keys' => [],
                'member_keys' => [],
                'sharing_total' => 0,
                'sharing_count' => 0,
                'absent_total' => 0,
                'quality_points' => 0,
                'quality_max' => 0,
            ];
        } elseif ((string) ($leaderSummaries[$leaderKey]['name'] ?? '') === '-' && $leaderName !== '-') {
            $leaderSummaries[$leaderKey]['name'] = $leaderName;
        }

        $groupId = trim((string) ($row['group_id'] ?? ''));
        $groupName = trim((string) ($row['group_name'] ?? ''));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $groupKey = $groupId !== '' ? $groupId : strtolower($groupName.'|'.$leaderKey);
        if ($groupKey === '') {
            $groupKey = 'group_unknown_'.$leaderKey;
        }

        if (! isset($groupSummaries[$groupKey])) {
            $groupSummaries[$groupKey] = [
                'group_key' => $groupKey,
                'group_id' => $groupId,
                'name' => $groupName,
                'leader_id' => $leaderId,
                'members_label' => 'Belum ada anggota',
                'leader_name' => $leaderName,
                'progress' => $normalizeProgressLabel((string) ($row['group_progress'] ?? '')),
                'report_count' => 0,
                'sharing_total' => 0,
                'sharing_count' => 0,
                'absent_total' => 0,
                'last_meeting_date' => '',
                'last_material_topic' => '',
                'last_report_created_at' => '',
            ];
        } elseif ((string) ($groupSummaries[$groupKey]['leader_name'] ?? '') === '-' && $leaderName !== '-') {
            $groupSummaries[$groupKey]['leader_name'] = $leaderName;
        }
        if ((string) ($groupSummaries[$groupKey]['group_id'] ?? '') === '' && $groupId !== '') {
            $groupSummaries[$groupKey]['group_id'] = $groupId;
        }
        if ((string) ($groupSummaries[$groupKey]['leader_id'] ?? '') === '' && $leaderId !== '') {
            $groupSummaries[$groupKey]['leader_id'] = $leaderId;
        }

        $sharingOpenness = $row['sharing_openness'] ?? null;
        $absentMemberCount = (int) ($row['absent_member_count'] ?? 0);
        $qualityScore = (int) ($row['quality_score'] ?? 0);
        $leaderSummaries[$leaderKey]['report_count']++;
        $leaderSummaries[$leaderKey]['group_keys'][$groupKey] = true;
        $leaderSummaries[$leaderKey]['absent_total'] += $absentMemberCount;
        $leaderSummaries[$leaderKey]['quality_points'] += $qualityScore;
        $leaderSummaries[$leaderKey]['quality_max'] += 4;

        $groupSummaries[$groupKey]['report_count']++;
        $groupSummaries[$groupKey]['absent_total'] += $absentMemberCount;
        $meetingDate = (string) ($row['meeting_date'] ?? '');
        $reportCreatedAt = (string) ($row['created_at'] ?? '');
        $materialTopic = trim((string) ($row['material_topic'] ?? ''));
        $latestMeetingDate = (string) ($groupSummaries[$groupKey]['last_meeting_date'] ?? '');
        $latestCreatedAt = (string) ($groupSummaries[$groupKey]['last_report_created_at'] ?? '');
        $isLatestReport = false;
        if ($meetingDate !== '') {
            if ($latestMeetingDate === '' || strcmp($meetingDate, $latestMeetingDate) > 0) {
                $isLatestReport = true;
            } elseif ($meetingDate === $latestMeetingDate && strcmp($reportCreatedAt, $latestCreatedAt) > 0) {
                $isLatestReport = true;
            }
        }
        if ($isLatestReport) {
            $groupSummaries[$groupKey]['last_meeting_date'] = $meetingDate;
            $groupSummaries[$groupKey]['last_report_created_at'] = $reportCreatedAt;
            $groupSummaries[$groupKey]['last_material_topic'] = $materialTopic;
        } elseif ((string) ($groupSummaries[$groupKey]['last_material_topic'] ?? '') === '' && $materialTopic !== '') {
            $groupSummaries[$groupKey]['last_material_topic'] = $materialTopic;
        }
        if ($groupSummaries[$groupKey]['progress'] === '') {
            $candidateProgress = $normalizeProgressLabel((string) ($row['group_progress'] ?? ''));
            if ($candidateProgress !== '') {
                $groupSummaries[$groupKey]['progress'] = $candidateProgress;
            }
        }

        if ($sharingOpenness !== null) {
            $leaderSummaries[$leaderKey]['sharing_total'] += (int) $sharingOpenness;
            $leaderSummaries[$leaderKey]['sharing_count']++;
            $groupSummaries[$groupKey]['sharing_total'] += (int) $sharingOpenness;
            $groupSummaries[$groupKey]['sharing_count']++;
        }

        $totalAbsentMembers += $absentMemberCount;
    }

    $leaderRows = array_values($leaderSummaries);
    foreach ($leaderRows as &$leaderRow) {
        $leaderRow['group_count'] = count($leaderRow['group_keys']);
        $leaderRow['member_count'] = count($leaderRow['member_keys'] ?? []);
    }
    unset($leaderRow);
    usort($leaderRows, function ($a, $b) {
        $cmpCount = (int) ($b['report_count'] ?? 0) <=> (int) ($a['report_count'] ?? 0);
        if ($cmpCount !== 0) {
            return $cmpCount;
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $groupRows = array_values($groupSummaries);
    usort($groupRows, function ($a, $b) {
        $cmpCount = (int) ($b['report_count'] ?? 0) <=> (int) ($a['report_count'] ?? 0);
        if ($cmpCount !== 0) {
            return $cmpCount;
        }
        $cmpDate = strcmp((string) ($b['last_meeting_date'] ?? ''), (string) ($a['last_meeting_date'] ?? ''));
        if ($cmpDate !== 0) {
            return $cmpDate;
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $totalReports = count($reportsPrepared);
    $totalLeaders = count($leaderRows);
    $totalGroups = count($groupRows);
    $buildReportLeaderFilterKey = function (array $reportRow): string {
        $leaderId = trim((string) ($reportRow['leader_id'] ?? ''));
        if ($leaderId !== '') {
            return 'id:'.$leaderId;
        }
        $leaderName = trim((string) ($reportRow['leader_name'] ?? ''));
        if ($leaderName === '') {
            $leaderName = '-';
        }

        return 'name:'.$leaderName;
    };
    $buildReportGroupFilterKey = function (array $reportRow) use ($buildReportLeaderFilterKey): string {
        $groupId = trim((string) ($reportRow['group_id'] ?? ''));
        if ($groupId !== '') {
            return 'id:'.$groupId;
        }
        $groupName = trim((string) ($reportRow['group_name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }

        return 'name:'.$groupName.'|'.$buildReportLeaderFilterKey($reportRow);
    };
    $reportLeaderOptions = [];
    $reportGroupOptions = [];
    $groupMembersByFilterKey = [];
    $groupMemberFirstNamesByFilterKey = [];
    foreach ($groups as $groupRow) {
        if (! is_array($groupRow)) {
            continue;
        }
        $groupId = trim((string) ($groupRow['id'] ?? ''));
        $groupName = trim((string) ($groupRow['name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $groupLeaderId = trim((string) ($groupRow['leader_id'] ?? ''));
        $groupLeaderName = person_label($peopleById, $groupLeaderId, '-');
        $groupFilterKey = $buildReportGroupFilterKey([
            'group_id' => $groupId,
            'group_name' => $groupName,
            'leader_id' => $groupLeaderId,
            'leader_name' => $groupLeaderName,
        ]);

        $memberNames = [];
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
                $memberNames[] = $memberName;
                $memberFirstName = $extractFirstName($memberName);
                if ($memberFirstName !== '') {
                    $memberFirstNames[] = $memberFirstName;
                }
            }
        }
        $groupMembersByFilterKey[$groupFilterKey] = count($memberNames) > 0 ? implode(', ', $memberNames) : 'Belum ada anggota';
        $groupMemberFirstNamesByFilterKey[$groupFilterKey] = count($memberFirstNames) > 0 ? implode(', ', $memberFirstNames) : 'Belum ada anggota';
    }
    foreach ($groupRows as &$groupRow) {
        if (! is_array($groupRow)) {
            continue;
        }
        $groupRow['report_group_key'] = $buildReportGroupFilterKey([
            'group_id' => $groupRow['group_id'] ?? '',
            'group_name' => $groupRow['name'] ?? '',
            'leader_id' => $groupRow['leader_id'] ?? '',
            'leader_name' => $groupRow['leader_name'] ?? '',
        ]);
    }
    unset($groupRow);
    $groupReportRowsByFilterKey = [];
    $reportCalendarRowsByDate = [];
    $reportCalendarRowsByMonth = [];
    foreach ($reportsPrepared as $reportRow) {
        if (! is_array($reportRow)) {
            continue;
        }
        $leaderKey = $buildReportLeaderFilterKey($reportRow);
        $leaderName = trim((string) ($reportRow['leader_name'] ?? ''));
        if ($leaderName === '') {
            $leaderName = '-';
        }
        if (! isset($reportLeaderOptions[$leaderKey])) {
            $reportLeaderOptions[$leaderKey] = $leaderName;
        }

        $groupKey = $buildReportGroupFilterKey($reportRow);
        if (! isset($groupReportRowsByFilterKey[$groupKey])) {
            $groupReportRowsByFilterKey[$groupKey] = [];
        }
        $groupName = trim((string) ($reportRow['group_name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $groupMembersLabel = (string) ($groupMembersByFilterKey[$groupKey] ?? 'Belum ada anggota');
        if (! isset($reportGroupOptions[$groupKey])) {
            $reportGroupOptions[$groupKey] = [
                'label' => $groupName,
                'members_label' => $groupMembersLabel,
                'leader_key' => $leaderKey,
            ];
        }

        $meetingDate = trim((string) ($reportRow['meeting_date'] ?? ''));
        $meetingDateLabel = $meetingDate !== '' ? format_indo_date($meetingDate) : '-';
        $groupFirstNamesLabel = trim((string) ($groupMemberFirstNamesByFilterKey[$groupKey] ?? 'Belum ada anggota'));
        if ($groupFirstNamesLabel === '') {
            $groupFirstNamesLabel = 'Belum ada anggota';
        }
        $groupProgressLabel = trim((string) ($reportRow['group_progress'] ?? ''));
        $absentMemberNames = $reportRow['absent_member_names'] ?? [];
        if (! is_array($absentMemberNames)) {
            $absentMemberNames = [];
        }
        $absentMemberCount = (int) ($reportRow['absent_member_count'] ?? 0);
        $absenceReason = trim((string) ($reportRow['absence_reason'] ?? ''));
        if ($absentMemberCount <= 0) {
            $absentLabel = '-';
            $absentReasonLabel = '';
        } elseif (count($absentMemberNames) > 0) {
            $absentLabel = implode(', ', $absentMemberNames);
            $absentReasonLabel = $absenceReason;
        } else {
            $absentLabel = (string) $absentMemberCount.' anggota';
            $absentReasonLabel = $absenceReason;
        }

        $meditationSharerNames = $reportRow['meditation_sharer_names'] ?? [];
        if (! is_array($meditationSharerNames)) {
            $meditationSharerNames = [];
        }
        $meditationMinTimes = (int) ($reportRow['meditation_min_times'] ?? 2);
        if ($meditationMinTimes <= 0) {
            $meditationMinTimes = 2;
        }
        $meditationLabel = count($meditationSharerNames) > 0 ? implode(', ', $meditationSharerNames) : '-';
        $meditationMetaLabel = count($meditationSharerNames) > 0 ? 'Minimal '.$meditationMinTimes.' kali' : '';

        $sharingLabel = '-';
        if (($reportRow['sharing_openness'] ?? null) !== null) {
            $sharingLabel = (string) ((int) $reportRow['sharing_openness']).' / 10';
        }

        $qualityTags = [];
        if (parse_bool_value($reportRow['quality_prepare'] ?? false)) {
            $qualityTags[] = 'Persiapan Materi';
        }
        if (parse_bool_value($reportRow['quality_pray'] ?? false)) {
            $qualityTags[] = 'Mendoakan Anggota';
        }
        if (parse_bool_value($reportRow['quality_share_meditation'] ?? false)) {
            $qualityTags[] = 'Share Meditasi';
        }
        if (parse_bool_value($reportRow['quality_relational'] ?? false)) {
            $qualityTags[] = 'Komunikasi Relasional';
        }

        $meetingPhotos = $reportRow['meeting_photos'] ?? [];
        if (! is_array($meetingPhotos)) {
            $meetingPhotos = [];
        }

        $groupReportRowsByFilterKey[$groupKey][] = [
            'meeting_date' => $meetingDateLabel,
            'leader_name' => $leaderName,
            'group_label' => $groupFirstNamesLabel,
            'group_progress' => $groupProgressLabel,
            'material_topic' => trim((string) ($reportRow['material_topic'] ?? '-')) ?: '-',
            'absent_label' => $absentLabel,
            'absent_reason_label' => $absentReasonLabel,
            'sharing_label' => $sharingLabel,
            'quality_tags' => $qualityTags,
            'meditation_label' => $meditationLabel,
            'meditation_meta_label' => $meditationMetaLabel,
            'notes_label' => trim((string) ($reportRow['additional_notes'] ?? '-')) ?: '-',
            'meeting_photos' => $meetingPhotos,
        ];
        if ($meetingDate !== '') {
            $meetingMonth = substr($meetingDate, 0, 7);
            if (! isset($reportCalendarRowsByDate[$meetingDate])) {
                $reportCalendarRowsByDate[$meetingDate] = [];
            }
            $calendarRow = [
                'meeting_date' => $meetingDateLabel,
                'leader_name' => $leaderName,
                'group_label' => $groupFirstNamesLabel,
                'group_progress' => $groupProgressLabel,
                'material_topic' => trim((string) ($reportRow['material_topic'] ?? '-')) ?: '-',
                'absent_label' => $absentLabel,
                'absent_reason_label' => $absentReasonLabel,
                'sharing_label' => $sharingLabel,
                'quality_tags' => $qualityTags,
                'meditation_label' => $meditationLabel,
                'meditation_meta_label' => $meditationMetaLabel,
                'notes_label' => trim((string) ($reportRow['additional_notes'] ?? '-')) ?: '-',
                'meeting_photos' => $meetingPhotos,
            ];
            $reportCalendarRowsByDate[$meetingDate][] = $calendarRow;
            if (! isset($reportCalendarRowsByMonth[$meetingMonth])) {
                $reportCalendarRowsByMonth[$meetingMonth] = [];
            }
            $reportCalendarRowsByMonth[$meetingMonth][] = $calendarRow;
        }
    }
    if (count($reportLeaderOptions) > 1) {
        asort($reportLeaderOptions, SORT_NATURAL | SORT_FLAG_CASE);
    }

    $selectedReportLeader = trim((string) ($_GET['report_leader'] ?? ''));
    if ($selectedReportLeader !== '' && ! isset($reportLeaderOptions[$selectedReportLeader])) {
        $selectedReportLeader = '';
    }

    $availableReportGroupOptions = [];
    if ($selectedReportLeader !== '') {
        foreach ($reportGroupOptions as $groupKey => $groupMeta) {
            if (! is_array($groupMeta)) {
                continue;
            }
            $groupLeaderKey = (string) ($groupMeta['leader_key'] ?? '');
            if ($groupLeaderKey !== $selectedReportLeader) {
                continue;
            }
            $groupLabel = (string) ($groupMeta['label'] ?? 'Kelompok');
            $groupMembersLabel = trim((string) ($groupMeta['members_label'] ?? ''));
            if ($groupMembersLabel === '') {
                $groupMembersLabel = 'Belum ada anggota';
            }
            $availableReportGroupOptions[$groupKey] = $groupLabel.' - '.$groupMembersLabel;
        }
        if (count($availableReportGroupOptions) > 1) {
            asort($availableReportGroupOptions, SORT_NATURAL | SORT_FLAG_CASE);
        }
    }

    $selectedReportGroup = trim((string) ($_GET['report_group'] ?? ''));
    if ($selectedReportLeader === '') {
        $selectedReportGroup = '';
    } elseif ($selectedReportGroup !== '') {
        if (! isset($reportGroupOptions[$selectedReportGroup])) {
            $selectedReportGroup = '';
        } elseif ((string) ($reportGroupOptions[$selectedReportGroup]['leader_key'] ?? '') !== $selectedReportLeader) {
            $selectedReportGroup = '';
        }
    }

    $reportsPreparedFiltered = array_values(array_filter($reportsPrepared, function ($reportRow) use ($buildReportLeaderFilterKey, $buildReportGroupFilterKey, $selectedReportLeader, $selectedReportGroup): bool {
        if (! is_array($reportRow)) {
            return false;
        }
        if ($selectedReportLeader !== '' && $buildReportLeaderFilterKey($reportRow) !== $selectedReportLeader) {
            return false;
        }
        if ($selectedReportGroup !== '' && $buildReportGroupFilterKey($reportRow) !== $selectedReportGroup) {
            return false;
        }

        return true;
    }));
    $totalReportRows = count($reportsPreparedFiltered);
    $reportsPerPage = max(1, min(100, (int) ($_GET['per_page'] ?? 25)));
    $currentReportPage = (int) ($_GET['report_page'] ?? 1);
    if ($currentReportPage < 1) {
        $currentReportPage = 1;
    }
    $totalReportPages = max(1, (int) ceil($totalReportRows / $reportsPerPage));
    if ($currentReportPage > $totalReportPages) {
        $currentReportPage = $totalReportPages;
    }
    $reportOffset = ($currentReportPage - 1) * $reportsPerPage;
    $reportsPreparedPage = array_slice($reportsPreparedFiltered, $reportOffset, $reportsPerPage);
    $reportPageHref = function (int $targetPage) use ($totalReportPages, $selectedReportLeader, $selectedReportGroup, $reportsPerPage): string {
        $pageNumber = max(1, min($totalReportPages, $targetPage));
        $query = [
            'report_page' => (string) $pageNumber,
            'per_page' => (string) $reportsPerPage,
        ];
        if ($selectedReportLeader !== '') {
            $query['report_leader'] = $selectedReportLeader;
        }
        if ($selectedReportGroup !== '') {
            $query['report_group'] = $selectedReportGroup;
        }

        return route('discipleship.reports-recap', $query).'#dg-recap-report-list';
    };
    $reportCalendarByMonth = [];
    $calendarMinMonth = '';
    $calendarMaxMonth = '';
    foreach ($reportsPrepared as $reportRow) {
        if (! is_array($reportRow)) {
            continue;
        }
        $meetingDate = trim((string) ($reportRow['meeting_date'] ?? ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $meetingDate)) {
            continue;
        }
        $monthKey = substr($meetingDate, 0, 7);
        $dayKey = substr($meetingDate, 8, 2);
        if ($calendarMinMonth === '' || strcmp($monthKey, $calendarMinMonth) < 0) {
            $calendarMinMonth = $monthKey;
        }
        if ($calendarMaxMonth === '' || strcmp($monthKey, $calendarMaxMonth) > 0) {
            $calendarMaxMonth = $monthKey;
        }
        if (! isset($reportCalendarByMonth[$monthKey])) {
            $reportCalendarByMonth[$monthKey] = [];
        }
        if (! isset($reportCalendarByMonth[$monthKey][$dayKey])) {
            $reportCalendarByMonth[$monthKey][$dayKey] = [
                'count' => 0,
                'leaders' => [],
            ];
        }
        $reportCalendarByMonth[$monthKey][$dayKey]['count']++;
        $leaderName = trim((string) ($reportRow['leader_name'] ?? ''));
        if ($leaderName !== '' && ! in_array($leaderName, $reportCalendarByMonth[$monthKey][$dayKey]['leaders'], true)) {
            $reportCalendarByMonth[$monthKey][$dayKey]['leaders'][] = $leaderName;
        }
    }
    $defaultCalendarMonth = $calendarMaxMonth !== '' ? $calendarMaxMonth : date('Y-m');
    $recapProgressFilterCounts = ['all' => count($groupRows), 'dg1' => 0, 'dg2' => 0, 'dg3' => 0];
    foreach ($groupRows as $groupRow) {
        $progressKey = strtolower(str_replace(' ', '', $normalizeProgressLabel((string) ($groupRow['progress'] ?? ''))));
        if (isset($recapProgressFilterCounts[$progressKey])) {
            $recapProgressFilterCounts[$progressKey]++;
        }
    }

    echo view('discipleship.partials.page-header', [
        'header' => [
            'tools' => [
                'element' => 'div',
                'partial' => 'discipleship.partials.page-header-controls.meeting-recap',
                'data' => compact('recapProgressFilterCounts'),
            ],
        ],
    ])->render();

    ob_start();
    echo "      <div class=\"dg-recap-calendar-toolbar\">\n";
    echo '        <div class="dg-recap-calendar-nav" data-dg-calendar-nav data-report-map="'.h(json_encode($reportCalendarByMonth, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}').'" data-default-month="'.h($defaultCalendarMonth)."\">\n";
    echo "          <button class=\"btn tiny ghost dg-recap-calendar-nav-btn\" type=\"button\" data-dg-calendar-prev aria-label=\"Bulan sebelumnya\">&lsaquo;</button>\n";
    echo '          <button class="dg-recap-calendar-nav-title" type="button" data-dg-calendar-title>'.h(format_indo_month($defaultCalendarMonth))."</button>\n";
    echo '          <input class="dg-recap-calendar-month-input" type="month" value="'.h($defaultCalendarMonth)."\" data-dg-calendar-month-input>\n";
    echo "          <button class=\"btn tiny ghost dg-recap-calendar-nav-btn\" type=\"button\" data-dg-calendar-next aria-label=\"Bulan sesudahnya\">&rsaquo;</button>\n";
    echo "        </div>\n";
    echo "        <div class=\"dg-recap-calendar-toolbar-side\">\n";
    echo "          <button class=\"btn secondary tiny dg-recap-calendar-month-report-btn\" type=\"button\" data-dg-calendar-month-report-open>Lihat Laporan Bulan Ini</button>\n";
    echo "          <div class=\"dg-recap-calendar-legend\"><span class=\"dg-recap-calendar-dot\"></span> Ada laporan pada tanggal ini</div>\n";
    echo "        </div>\n";
    echo "      </div>\n";
    echo "      <div class=\"dg-recap-calendar-panels\" data-dg-calendar-panels></div>\n";
    $calendarBodyHtml = ob_get_clean();
    echo view('partials.modal', [
        'id' => 'dg-recap-calendar-modal',
        'size' => 'wide',
        'modalAttrs' => ['data-dg-recap-calendar-modal' => true],
        'cardClass' => 'dg-recap-calendar-modal-card',
        'title' => 'Kalender Laporan DG',
        'subtitleHtml' => '<div class="dg-recap-subtext">Tanggal yang ditandai menunjukkan adanya laporan pertemuan DG.</div>',
        'closeAttrs' => ['data-dg-calendar-close' => true],
        'bodyClass' => 'dg-recap-calendar-modal-body',
        'bodyHtml' => $calendarBodyHtml,
    ])->render();
    echo view('partials.modal', [
        'id' => 'dg-recap-month-report-modal',
        'size' => 'wide',
        'modalAttrs' => ['data-dg-recap-month-report-modal' => true],
        'cardClass' => 'dg-recap-group-report-modal-card',
        'title' => 'Laporan Bulanan',
        'titleAttrs' => ['data-dg-recap-month-report-title' => true],
        'subtitleHtml' => '<div class="dg-recap-subtext" data-dg-recap-month-report-meta></div>',
        'closeAttrs' => ['data-dg-recap-month-report-close' => true],
        'bodyAttrs' => ['data-dg-recap-month-report-body' => true],
        'bodyHtml' => '<div class="panel-note">Gunakan tombol laporan bulanan pada modal kalender untuk melihat daftar laporan pada bulan aktif.</div>',
    ])->render();
    echo view('partials.modal', [
        'id' => 'dg-recap-date-report-modal',
        'size' => 'wide',
        'modalAttrs' => ['data-dg-recap-date-report-modal' => true],
        'cardClass' => 'dg-recap-group-report-modal-card',
        'title' => 'Laporan Tanggal',
        'titleAttrs' => ['data-dg-recap-date-report-title' => true],
        'subtitleHtml' => '<div class="dg-recap-subtext" data-dg-recap-date-report-meta></div>',
        'closeAttrs' => ['data-dg-recap-date-report-close' => true],
        'bodyAttrs' => ['data-dg-recap-date-report-body' => true],
        'bodyHtml' => '<div class="panel-note">Klik tanggal yang memiliki laporan pada kalender untuk melihat detailnya.</div>',
    ])->render();
    echo view('partials.modal', [
        'id' => 'dg-recap-photo-modal',
        'size' => 'media',
        'modalAttrs' => ['data-dg-recap-photo-modal' => true],
        'cardClass' => 'file-view-modal-card',
        'title' => 'Preview Foto',
        'titleAttrs' => ['data-dg-recap-photo-title' => true],
        'closeAttrs' => ['data-dg-recap-photo-close' => true],
        'bodyClass' => 'file-view-body',
        'bodyHtml' => '<div class="file-view-image-wrap" data-dg-recap-photo-wrap><img class="file-view-image" src="" alt="Preview Foto" data-dg-recap-photo-image></div>',
    ])->render();
    foreach ($reportCalendarRowsByDate as $reportDate => $dateRows) {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $reportDate)) {
            continue;
        }
        echo '<template data-dg-recap-date-report-template="'.h($reportDate).'">';
        if (count($dateRows) === 0) {
            echo '<div class="panel-note">Belum ada laporan pada tanggal ini.</div>';
        } else {
            echo '<div class="table-wrap dg-recap-group-report-table-wrap"><table class="table dg-recap-table dg-recap-group-report-table"><thead><tr><th>Tanggal</th><th>Pemimpin</th><th>Anggota</th><th>Materi</th><th>Anggota Tidak Hadir</th><th>Kualitas Pemimpin</th><th>Sharing</th><th>Pembagi Meditasi</th><th>Catatan</th><th>Foto</th></tr></thead><tbody>';
            foreach ($dateRows as $modalRow) {
                $qualityTags = $modalRow['quality_tags'] ?? [];
                if (! is_array($qualityTags)) {
                    $qualityTags = [];
                }
                $meetingPhotos = $modalRow['meeting_photos'] ?? [];
                if (! is_array($meetingPhotos)) {
                    $meetingPhotos = [];
                }
                echo '<tr>';
                echo '<td class="dg-recap-text"><div class="dg-recap-main-cell"><div class="dg-recap-main-title">'.h((string) ($modalRow['meeting_date'] ?? '-')).'</div></div></td>';
                echo '<td>'.h((string) ($modalRow['leader_name'] ?? '-')).'</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['group_label'] ?? 'Belum ada anggota'));
                $groupProgressLabel = trim((string) ($modalRow['group_progress'] ?? ''));
                if ($groupProgressLabel !== '') {
                    echo '<div class="dg-recap-subtext">Progress: '.h($groupProgressLabel).'</div>';
                }
                echo '</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['material_topic'] ?? '-')).'</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['absent_label'] ?? '-'));
                $absentReasonLabel = trim((string) ($modalRow['absent_reason_label'] ?? ''));
                if ($absentReasonLabel !== '') {
                    echo '<div class="dg-recap-subtext">Alasan: '.h($absentReasonLabel).'</div>';
                }
                echo '</td>';
                echo '<td>';
                if (count($qualityTags) === 0) {
                    echo '-';
                } else {
                    echo '<div class="dg-recap-chip-list">';
                    foreach ($qualityTags as $tag) {
                        echo '<span class="chip">'.h((string) $tag).'</span>';
                    }
                    echo '</div>';
                }
                echo '</td>';
                echo '<td><div class="dg-recap-number-chip">'.h((string) ($modalRow['sharing_label'] ?? '-')).'</div></td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['meditation_label'] ?? '-'));
                $meditationMetaLabel = trim((string) ($modalRow['meditation_meta_label'] ?? ''));
                if ($meditationMetaLabel !== '') {
                    echo '<div class="dg-recap-subtext">'.h($meditationMetaLabel).'</div>';
                }
                echo '</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['notes_label'] ?? '-')).'</td>';
                echo '<td class="dg-recap-text">';
                $photoCount = count($meetingPhotos);
                if ($photoCount <= 0) {
                    echo '-';
                } else {
                    echo h((string) $photoCount.' foto');
                    $photoPreview = array_slice($meetingPhotos, 0, 3);
                    if (count($photoPreview) > 0) {
                        echo '<div class="dg-recap-photo-links">';
                        foreach ($photoPreview as $photoRow) {
                            $photoPath = trim((string) ($photoRow['path'] ?? ''));
                            if ($photoPath === '') {
                                continue;
                            }
                            $photoUrl = secure_upload_url($photoPath);
                            if ($photoUrl === '') {
                                continue;
                            }
                            echo '<button class="note-link dg-recap-photo-trigger" type="button" data-dg-recap-photo-open data-photo-src="'.h($photoUrl).'" data-photo-title="Preview Foto">Lihat Foto</button>';
                        }
                        echo '</div>';
                    }
                    if ($photoCount > 3) {
                        echo '<div class="dg-recap-subtext">+'.h((string) ($photoCount - 3)).' foto lainnya</div>';
                    }
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo "</template>\n";
    }
    foreach ($reportCalendarRowsByMonth as $reportMonth => $monthRows) {
        if (! preg_match('/^\d{4}-\d{2}$/', (string) $reportMonth)) {
            continue;
        }
        echo '<template data-dg-recap-month-report-template="'.h($reportMonth).'">';
        if (count($monthRows) === 0) {
            echo '<div class="panel-note">Belum ada laporan pada bulan ini.</div>';
        } else {
            echo '<div class="table-wrap dg-recap-group-report-table-wrap"><table class="table dg-recap-table dg-recap-group-report-table"><thead><tr><th>Tanggal</th><th>Pemimpin</th><th>Anggota</th><th>Materi</th><th>Anggota Tidak Hadir</th><th>Kualitas Pemimpin</th><th>Sharing</th><th>Pembagi Meditasi</th><th>Catatan</th><th>Foto</th></tr></thead><tbody>';
            foreach ($monthRows as $modalRow) {
                $qualityTags = $modalRow['quality_tags'] ?? [];
                if (! is_array($qualityTags)) {
                    $qualityTags = [];
                }
                $meetingPhotos = $modalRow['meeting_photos'] ?? [];
                if (! is_array($meetingPhotos)) {
                    $meetingPhotos = [];
                }
                echo '<tr>';
                echo '<td class="dg-recap-text"><div class="dg-recap-main-cell"><div class="dg-recap-main-title">'.h((string) ($modalRow['meeting_date'] ?? '-')).'</div></div></td>';
                echo '<td>'.h((string) ($modalRow['leader_name'] ?? '-')).'</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['group_label'] ?? 'Belum ada anggota'));
                $groupProgressLabel = trim((string) ($modalRow['group_progress'] ?? ''));
                if ($groupProgressLabel !== '') {
                    echo '<div class="dg-recap-subtext">Progress: '.h($groupProgressLabel).'</div>';
                }
                echo '</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['material_topic'] ?? '-')).'</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['absent_label'] ?? '-'));
                $absentReasonLabel = trim((string) ($modalRow['absent_reason_label'] ?? ''));
                if ($absentReasonLabel !== '') {
                    echo '<div class="dg-recap-subtext">Alasan: '.h($absentReasonLabel).'</div>';
                }
                echo '</td>';
                echo '<td>';
                if (count($qualityTags) === 0) {
                    echo '-';
                } else {
                    echo '<div class="dg-recap-chip-list">';
                    foreach ($qualityTags as $tag) {
                        echo '<span class="chip">'.h((string) $tag).'</span>';
                    }
                    echo '</div>';
                }
                echo '</td>';
                echo '<td><div class="dg-recap-number-chip">'.h((string) ($modalRow['sharing_label'] ?? '-')).'</div></td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['meditation_label'] ?? '-'));
                $meditationMetaLabel = trim((string) ($modalRow['meditation_meta_label'] ?? ''));
                if ($meditationMetaLabel !== '') {
                    echo '<div class="dg-recap-subtext">'.h($meditationMetaLabel).'</div>';
                }
                echo '</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['notes_label'] ?? '-')).'</td>';
                echo '<td class="dg-recap-text">';
                $photoCount = count($meetingPhotos);
                if ($photoCount <= 0) {
                    echo '-';
                } else {
                    echo h((string) $photoCount.' foto');
                    $photoPreview = array_slice($meetingPhotos, 0, 3);
                    if (count($photoPreview) > 0) {
                        echo '<div class="dg-recap-photo-links">';
                        foreach ($photoPreview as $photoRow) {
                            $photoPath = trim((string) ($photoRow['path'] ?? ''));
                            if ($photoPath === '') {
                                continue;
                            }
                            $photoUrl = secure_upload_url($photoPath);
                            if ($photoUrl === '') {
                                continue;
                            }
                            echo '<button class="note-link dg-recap-photo-trigger" type="button" data-dg-recap-photo-open data-photo-src="'.h($photoUrl).'" data-photo-title="Preview Foto">Lihat Foto</button>';
                        }
                        echo '</div>';
                    }
                    if ($photoCount > 3) {
                        echo '<div class="dg-recap-subtext">+'.h((string) ($photoCount - 3)).' foto lainnya</div>';
                    }
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo "</template>\n";
    }

    usort($groupRows, function ($a, $b) use ($normalizeProgressLabel) {
        $progressRank = static function (string $progress) use ($normalizeProgressLabel): int {
            $normalized = $normalizeProgressLabel($progress);
            if ($normalized === 'DG 1') {
                return 1;
            }
            if ($normalized === 'DG 2') {
                return 2;
            }
            if ($normalized === 'DG 3') {
                return 3;
            }

            return 9;
        };
        $rankA = $progressRank((string) ($a['progress'] ?? ''));
        $rankB = $progressRank((string) ($b['progress'] ?? ''));
        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        return strcasecmp((string) ($a['leader_name'] ?? ''), (string) ($b['leader_name'] ?? ''));
    });

    echo "<section class=\"card dg-recap-section-card\">\n";
    echo "  <div class=\"table-wrap\" data-dg-recap-summary-scroll data-table-horizontal-scroll>\n";
    echo "    <table class=\"table dg-recap-table\" id=\"dg-recap-summary-table\">\n";
    echo "      <thead><tr><th>Pemimpin</th><th>Progress</th><th>Sesi Terakhir</th><th>Laporan</th><th>Laporan Terakhir</th></tr></thead>\n";
    echo "      <tbody>\n";
    foreach ($groupRows as $groupRow) {
        $progressLabel = $normalizeProgressLabel((string) ($groupRow['progress'] ?? ''));
        if ($progressLabel === '') {
            $progressLabel = '-';
        }
        $groupMembersLabel = trim((string) ($groupRow['members_label'] ?? ''));
        if ($groupMembersLabel === '') {
            $groupMembersLabel = 'Belum ada anggota';
        }
        $lastMaterialLabel = trim((string) ($groupRow['last_material_topic'] ?? ''));
        if ($lastMaterialLabel === '') {
            $lastMaterialLabel = '-';
        }
        $lastMeetingDate = trim((string) ($groupRow['last_meeting_date'] ?? ''));
        $lastMeetingLabel = $lastMeetingDate !== '' ? format_indo_date($lastMeetingDate) : '-';
        $progressClass = 'is-neutral';
        if ($progressLabel === 'DG 1') {
            $progressClass = 'is-dg1';
        } elseif ($progressLabel === 'DG 2') {
            $progressClass = 'is-dg2';
        } elseif ($progressLabel === 'DG 3') {
            $progressClass = 'is-dg3';
        }
        $progressFilter = strtolower(str_replace(' ', '', $progressLabel));
        echo '<tr data-recap-progress="'.h($progressFilter).'">';
        echo '<td><div class="dg-recap-main-cell"><div class="dg-recap-main-title">'.h((string) ($groupRow['leader_name'] ?? '-')).'</div><div class="dg-recap-subtext">'.h($groupMembersLabel).'</div></div></td>';
        echo '<td><span class="dg-recap-stage-pill '.h($progressClass).'">'.h($progressLabel).'</span></td>';
        $reportCount = (int) ($groupRow['report_count'] ?? 0);
        $reportGroupKey = (string) ($groupRow['report_group_key'] ?? '');
        echo '<td>'.h($lastMaterialLabel).'</td>';
        echo '<td>';
        if ($reportCount > 0 && $reportGroupKey !== '') {
            echo '<button class="dg-recap-number-chip is-accent dg-recap-report-trigger" type="button" data-dg-recap-modal-open data-group-key="'.h($reportGroupKey).'" data-group-title="'.h((string) ($groupRow['leader_name'] ?? '-')).'" data-group-progress="'.h($progressLabel).'" data-group-members="'.h($groupMembersLabel).'">'.h((string) $reportCount).'</button>';
        } else {
            echo '<div class="dg-recap-number-chip is-accent">'.h((string) $reportCount).'</div>';
        }
        echo '</td>';
        echo '<td>'.h($lastMeetingLabel).'</td>';
        echo "</tr>\n";
    }
    if (count($groupRows) === 0) {
        echo "<tr><td colspan=\"5\">Belum ada kelompok yang terdaftar.</td></tr>\n";
    }
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo view('partials.modal', [
        'id' => 'dg-recap-group-report-modal',
        'size' => 'wide',
        'modalAttrs' => ['data-dg-recap-group-report-modal' => true],
        'cardClass' => 'dg-recap-group-report-modal-card',
        'title' => 'Daftar Laporan Kelompok',
        'titleAttrs' => ['data-dg-recap-group-report-title' => true],
        'subtitleHtml' => '<div class="dg-recap-subtext" data-dg-recap-group-report-meta></div>',
        'closeAttrs' => ['data-dg-recap-group-report-close' => true],
        'bodyAttrs' => ['data-dg-recap-group-report-body' => true],
        'bodyHtml' => '<div class="panel-note">Klik jumlah laporan pada tabel ringkasan kelompok untuk membuka detail laporan kelompok tersebut.</div>',
    ])->render();
    foreach ($groupRows as $groupRow) {
        $reportGroupKey = (string) ($groupRow['report_group_key'] ?? '');
        if ($reportGroupKey === '') {
            continue;
        }
        $modalRows = $groupReportRowsByFilterKey[$reportGroupKey] ?? [];
        echo '<template data-dg-recap-group-report-template="'.h($reportGroupKey).'">';
        if (count($modalRows) === 0) {
            echo '<div class="panel-note">Belum ada laporan untuk kelompok ini.</div>';
        } else {
            echo '<div class="table-wrap dg-recap-group-report-table-wrap"><table class="table dg-recap-table dg-recap-group-report-table"><thead><tr><th>Tanggal</th><th>Pemimpin</th><th>Anggota</th><th>Materi</th><th>Anggota Tidak Hadir</th><th>Kualitas Pemimpin</th><th>Sharing</th><th>Pembagi Meditasi</th><th>Catatan</th><th>Foto</th></tr></thead><tbody>';
            foreach ($modalRows as $modalRow) {
                $qualityTags = $modalRow['quality_tags'] ?? [];
                if (! is_array($qualityTags)) {
                    $qualityTags = [];
                }
                $meetingPhotos = $modalRow['meeting_photos'] ?? [];
                if (! is_array($meetingPhotos)) {
                    $meetingPhotos = [];
                }
                echo '<tr>';
                echo '<td class="dg-recap-text"><div class="dg-recap-main-cell"><div class="dg-recap-main-title">'.h((string) ($modalRow['meeting_date'] ?? '-')).'</div></div></td>';
                echo '<td>'.h((string) ($modalRow['leader_name'] ?? '-')).'</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['group_label'] ?? 'Belum ada anggota'));
                $groupProgressLabel = trim((string) ($modalRow['group_progress'] ?? ''));
                if ($groupProgressLabel !== '') {
                    echo '<div class="dg-recap-subtext">Progress: '.h($groupProgressLabel).'</div>';
                }
                echo '</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['material_topic'] ?? '-')).'</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['absent_label'] ?? '-'));
                $absentReasonLabel = trim((string) ($modalRow['absent_reason_label'] ?? ''));
                if ($absentReasonLabel !== '') {
                    echo '<div class="dg-recap-subtext">Alasan: '.h($absentReasonLabel).'</div>';
                }
                echo '</td>';
                echo '<td>';
                if (count($qualityTags) === 0) {
                    echo '-';
                } else {
                    echo '<div class="dg-recap-chip-list">';
                    foreach ($qualityTags as $tag) {
                        echo '<span class="chip">'.h((string) $tag).'</span>';
                    }
                    echo '</div>';
                }
                echo '</td>';
                echo '<td><div class="dg-recap-number-chip">'.h((string) ($modalRow['sharing_label'] ?? '-')).'</div></td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['meditation_label'] ?? '-'));
                $meditationMetaLabel = trim((string) ($modalRow['meditation_meta_label'] ?? ''));
                if ($meditationMetaLabel !== '') {
                    echo '<div class="dg-recap-subtext">'.h($meditationMetaLabel).'</div>';
                }
                echo '</td>';
                echo '<td class="dg-recap-text">'.h((string) ($modalRow['notes_label'] ?? '-')).'</td>';
                echo '<td class="dg-recap-text">';
                $photoCount = count($meetingPhotos);
                if ($photoCount <= 0) {
                    echo '-';
                } else {
                    echo h((string) $photoCount.' foto');
                    $photoPreview = array_slice($meetingPhotos, 0, 3);
                    if (count($photoPreview) > 0) {
                        echo '<div class="dg-recap-photo-links">';
                        foreach ($photoPreview as $photoRow) {
                            $photoPath = trim((string) ($photoRow['path'] ?? ''));
                            if ($photoPath === '') {
                                continue;
                            }
                            $photoUrl = secure_upload_url($photoPath);
                            if ($photoUrl === '') {
                                continue;
                            }
                            echo '<button class="note-link dg-recap-photo-trigger" type="button" data-dg-recap-photo-open data-photo-src="'.h($photoUrl).'" data-photo-title="Preview Foto">Lihat Foto</button>';
                        }
                        echo '</div>';
                    }
                    if ($photoCount > 3) {
                        echo '<div class="dg-recap-subtext">+'.h((string) ($photoCount - 3)).' foto lainnya</div>';
                    }
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo "</template>\n";
    }

    echo "<script>\n";
    echo "(function(){\n";
    echo "  var groupModal = document.querySelector('[data-dg-recap-group-report-modal]');\n";
    echo "  var calendarModal = document.querySelector('[data-dg-recap-calendar-modal]');\n";
    echo "  var monthModal = document.querySelector('[data-dg-recap-month-report-modal]');\n";
    echo "  var dateModal = document.querySelector('[data-dg-recap-date-report-modal]');\n";
    echo "  var photoModal = document.querySelector('[data-dg-recap-photo-modal]');\n";
    echo "  var bindModal = function(modal){\n";
    echo "    if(!modal){ return { open: function(){}, close: function(){} }; }\n";
    echo "    var open = function(){ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); document.body.classList.add('modal-open'); };\n";
    echo "    var close = function(){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); if(!document.querySelector('.modal.is-open')){ document.body.classList.remove('modal-open'); } };\n";
    echo "    modal.addEventListener('click', function(event){ if(event.target === modal){ close(); } });\n";
    echo "    return { open: open, close: close };\n";
    echo "  };\n";
    echo "  var groupModalControl = bindModal(groupModal);\n";
    echo "  var calendarModalControl = bindModal(calendarModal);\n";
    echo "  var monthModalControl = bindModal(monthModal);\n";
    echo "  var dateModalControl = bindModal(dateModal);\n";
    echo "  var photoModalControl = bindModal(photoModal);\n";
    echo "  if(groupModal){\n";
    echo "    var title = groupModal.querySelector('[data-dg-recap-group-report-title]');\n";
    echo "    var meta = groupModal.querySelector('[data-dg-recap-group-report-meta]');\n";
    echo "    var body = groupModal.querySelector('[data-dg-recap-group-report-body]');\n";
    echo "    var closeBtn = groupModal.querySelector('[data-dg-recap-group-report-close]');\n";
    echo "    if(closeBtn){ closeBtn.addEventListener('click', groupModalControl.close); }\n";
    echo "    document.querySelectorAll('[data-dg-recap-modal-open]').forEach(function(trigger){\n";
    echo "    trigger.addEventListener('click', function(){\n";
    echo "      var groupKey = trigger.getAttribute('data-group-key') || '';\n";
    echo "      var leader = trigger.getAttribute('data-group-title') || 'Kelompok';\n";
    echo "      var progress = trigger.getAttribute('data-group-progress') || '-';\n";
    echo "      var members = trigger.getAttribute('data-group-members') || 'Belum ada anggota';\n";
    echo "      var template = null;\n";
    echo "      document.querySelectorAll('[data-dg-recap-group-report-template]').forEach(function(node){\n";
    echo "        if(!template && node.getAttribute('data-dg-recap-group-report-template') === groupKey){ template = node; }\n";
    echo "      });\n";
    echo "      if(title){ title.textContent = 'Laporan Kelompok - ' + leader; }\n";
    echo "      if(meta){ meta.textContent = progress + ' | ' + members; }\n";
    echo "      if(body){ body.innerHTML = template ? template.innerHTML : '<div class=\"panel-note\">Belum ada laporan untuk kelompok ini.</div>'; }\n";
    echo "      groupModalControl.open();\n";
    echo "    });\n";
    echo "  });\n";
    echo "  }\n";
    echo "  if(monthModal){\n";
    echo "    var monthTitle = monthModal.querySelector('[data-dg-recap-month-report-title]');\n";
    echo "    var monthMeta = monthModal.querySelector('[data-dg-recap-month-report-meta]');\n";
    echo "    var monthBody = monthModal.querySelector('[data-dg-recap-month-report-body]');\n";
    echo "    var monthCloseBtn = monthModal.querySelector('[data-dg-recap-month-report-close]');\n";
    echo "    if(monthCloseBtn){ monthCloseBtn.addEventListener('click', monthModalControl.close); }\n";
    echo "    document.querySelectorAll('[data-dg-calendar-month-report-open]').forEach(function(trigger){\n";
    echo "      trigger.addEventListener('click', function(){\n";
    echo "        var template = document.querySelector('[data-dg-recap-month-report-template=\"' + activeMonthValue + '\"]');\n";
    echo "        if(monthTitle){ monthTitle.textContent = 'Laporan Bulan ' + formatMonthLabel(activeMonthValue); }\n";
    echo "        if(monthMeta){ monthMeta.textContent = 'Daftar laporan pertemuan DG pada bulan aktif.'; }\n";
    echo "        if(monthBody){ monthBody.innerHTML = template ? template.innerHTML : '<div class=\"panel-note\">Belum ada laporan pada bulan ini.</div>'; }\n";
    echo "        monthModalControl.open();\n";
    echo "      });\n";
    echo "    });\n";
    echo "  }\n";
    echo "  if(dateModal){\n";
    echo "    var dateTitle = dateModal.querySelector('[data-dg-recap-date-report-title]');\n";
    echo "    var dateMeta = dateModal.querySelector('[data-dg-recap-date-report-meta]');\n";
    echo "    var dateBody = dateModal.querySelector('[data-dg-recap-date-report-body]');\n";
    echo "    var dateCloseBtn = dateModal.querySelector('[data-dg-recap-date-report-close]');\n";
    echo "    if(dateCloseBtn){ dateCloseBtn.addEventListener('click', dateModalControl.close); }\n";
    echo "    if(calendarModal){\n";
    echo "      calendarModal.addEventListener('click', function(event){\n";
    echo "        var trigger = event.target.closest('[data-dg-calendar-date]');\n";
    echo "        if(!trigger){ return; }\n";
    echo "        var reportDate = trigger.getAttribute('data-dg-calendar-date') || '';\n";
    echo "        var template = document.querySelector('[data-dg-recap-date-report-template=\"' + reportDate + '\"]');\n";
    echo "        if(!reportDate || !template){ return; }\n";
    echo "        if(dateTitle){ dateTitle.textContent = 'Laporan Tanggal ' + formatDateLabel(reportDate); }\n";
    echo "        if(dateMeta){ dateMeta.textContent = 'Daftar laporan pertemuan DG pada tanggal ini.'; }\n";
    echo "        if(dateBody){ dateBody.innerHTML = template.innerHTML; }\n";
    echo "        dateModalControl.open();\n";
    echo "      });\n";
    echo "    }\n";
    echo "  }\n";
    echo "  if(photoModal){\n";
    echo "    var photoTitle = photoModal.querySelector('[data-dg-recap-photo-title]');\n";
    echo "    var photoImage = photoModal.querySelector('[data-dg-recap-photo-image]');\n";
    echo "    var photoCloseBtn = photoModal.querySelector('[data-dg-recap-photo-close]');\n";
    echo "    if(photoCloseBtn){ photoCloseBtn.addEventListener('click', photoModalControl.close); }\n";
    echo "    document.addEventListener('click', function(event){\n";
    echo "      var trigger = event.target.closest('[data-dg-recap-photo-open]');\n";
    echo "      if(!trigger){ return; }\n";
    echo "      var src = trigger.getAttribute('data-photo-src') || '';\n";
    echo "      var titleText = trigger.getAttribute('data-photo-title') || 'Preview Foto';\n";
    echo "      if(!src || !photoImage){ return; }\n";
    echo "      if(photoTitle){ photoTitle.textContent = titleText; }\n";
    echo "      photoImage.src = src;\n";
    echo "      photoImage.alt = titleText;\n";
    echo "      photoModalControl.open();\n";
    echo "    });\n";
    echo "  }\n";
    echo "  if(calendarModal){\n";
    echo "    var calendarCloseBtn = calendarModal.querySelector('[data-dg-calendar-close]');\n";
    echo "    var nav = calendarModal.querySelector('[data-dg-calendar-nav]');\n";
    echo "    var titleNode = calendarModal.querySelector('[data-dg-calendar-title]');\n";
    echo "    var monthInput = calendarModal.querySelector('[data-dg-calendar-month-input]');\n";
    echo "    var prevBtn = calendarModal.querySelector('[data-dg-calendar-prev]');\n";
    echo "    var nextBtn = calendarModal.querySelector('[data-dg-calendar-next]');\n";
    echo "    var panels = calendarModal.querySelector('[data-dg-calendar-panels]');\n";
    echo "    var reportMap = {};\n";
    echo "    var activeMonthValue = '';\n";
    echo "    if(nav){\n";
    echo "      try { reportMap = JSON.parse(nav.getAttribute('data-report-map') || '{}') || {}; } catch (error) { reportMap = {}; }\n";
    echo "      activeMonthValue = nav.getAttribute('data-default-month') || '';\n";
    echo "    }\n";
    echo "    var formatMonthLabel = function(monthValue){\n";
    echo "      var parts = (monthValue || '').split('-');\n";
    echo "      var labels = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];\n";
    echo "      var monthIndex = parseInt(parts[1], 10) - 1;\n";
    echo "      var yearLabel = parts[0] || '';\n";
    echo "      return (labels[monthIndex] || monthValue) + (yearLabel ? ' ' + yearLabel : '');\n";
    echo "    };\n";
    echo "    var formatDateLabel = function(dateValue){\n";
    echo "      var parts = (dateValue || '').split('-');\n";
    echo "      if(parts.length !== 3){ return dateValue || ''; }\n";
    echo "      return parseInt(parts[2], 10) + ' ' + formatMonthLabel(parts[0] + '-' + parts[1]);\n";
    echo "    };\n";
    echo "    var shiftMonth = function(monthValue, offset){\n";
    echo "      var parts = (monthValue || '').split('-');\n";
    echo "      var year = parseInt(parts[0], 10);\n";
    echo "      var month = parseInt(parts[1], 10);\n";
    echo "      if(!year || !month){ var now = new Date(); year = now.getFullYear(); month = now.getMonth() + 1; }\n";
    echo "      var date = new Date(year, month - 1 + offset, 1);\n";
    echo "      return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');\n";
    echo "    };\n";
    echo "    var escapeHtml = function(value){\n";
    echo "      return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\\\"/g, '&quot;').replace(/'/g, '&#039;');\n";
    echo "    };\n";
    echo "    var renderCalendar = function(monthValue){\n";
    echo "      if(!panels){ return; }\n";
    echo "      activeMonthValue = monthValue || activeMonthValue;\n";
    echo "      var parts = (activeMonthValue || '').split('-');\n";
    echo "      var year = parseInt(parts[0], 10);\n";
    echo "      var month = parseInt(parts[1], 10);\n";
    echo "      if(!year || !month){ var now = new Date(); year = now.getFullYear(); month = now.getMonth() + 1; activeMonthValue = year + '-' + String(month).padStart(2, '0'); }\n";
    echo "      var firstDay = new Date(year, month - 1, 1);\n";
    echo "      var daysInMonth = new Date(year, month, 0).getDate();\n";
    echo "      var firstDayWeekday = firstDay.getDay();\n";
    echo "      firstDayWeekday = firstDayWeekday === 0 ? 7 : firstDayWeekday;\n";
    echo "      var monthData = reportMap[activeMonthValue] || {};\n";
    echo "      var html = '<div class=\"dg-recap-calendar-panel\">';\n";
    echo "      html += '<div class=\"dg-recap-calendar-grid dg-recap-calendar-grid-head\">';\n";
    echo "      ['Sen','Sel','Rab','Kam','Jum','Sab','Min'].forEach(function(label){ html += '<div class=\"dg-recap-calendar-weekday\">' + label + '</div>'; });\n";
    echo "      html += '</div><div class=\"dg-recap-calendar-grid dg-recap-calendar-grid-body\">';\n";
    echo "      var renderedCells = 0;\n";
    echo "      for(var blank = 1; blank < firstDayWeekday; blank += 1){ html += '<div class=\"dg-recap-calendar-day is-empty\"></div>'; renderedCells += 1; }\n";
    echo "      for(var day = 1; day <= daysInMonth; day += 1){\n";
    echo "        var dayKey = String(day).padStart(2, '0');\n";
    echo "        var dayData = monthData[dayKey] || null;\n";
    echo "        var fullDate = activeMonthValue + '-' + dayKey;\n";
    echo "        if(dayData){ html += '<button type=\"button\" class=\"dg-recap-calendar-day has-report\" data-dg-calendar-date=\"' + fullDate + '\">'; } else { html += '<div class=\"dg-recap-calendar-day\">'; }\n";
    echo "        html += '<div class=\"dg-recap-calendar-day-number\">' + day + '</div>';\n";
    echo "        if(dayData){\n";
    echo "          html += '<div class=\"dg-recap-calendar-day-count\">' + escapeHtml(dayData.count || 0) + '</div>';\n";
    echo "          if(Array.isArray(dayData.leaders) && dayData.leaders.length > 0){ html += '<div class=\"dg-recap-calendar-day-meta\">' + escapeHtml(dayData.leaders.slice(0,2).join(', ')) + '</div>'; }\n";
    echo "        }\n";
    echo "        html += dayData ? '</button>' : '</div>';\n";
    echo "        renderedCells += 1;\n";
    echo "      }\n";
    echo "      while(renderedCells < 42){ html += '<div class=\"dg-recap-calendar-day is-empty\"></div>'; renderedCells += 1; }\n";
    echo "      html += '</div></div>';\n";
    echo "      panels.innerHTML = html;\n";
    echo "      if(titleNode){ titleNode.textContent = formatMonthLabel(activeMonthValue); }\n";
    echo "      if(monthInput){ monthInput.value = activeMonthValue; }\n";
    echo "    };\n";
    echo "    if(calendarCloseBtn){ calendarCloseBtn.addEventListener('click', calendarModalControl.close); }\n";
    echo "    if(titleNode && monthInput){\n";
    echo "      titleNode.addEventListener('click', function(){\n";
    echo "        if(typeof monthInput.showPicker === 'function'){ monthInput.showPicker(); } else { monthInput.focus(); monthInput.click(); }\n";
    echo "      });\n";
    echo "      monthInput.addEventListener('change', function(){ if(monthInput.value){ renderCalendar(monthInput.value); } });\n";
    echo "    }\n";
    echo "    if(prevBtn){\n";
    echo "      prevBtn.addEventListener('click', function(){\n";
    echo "        renderCalendar(shiftMonth(activeMonthValue, -1));\n";
    echo "      });\n";
    echo "    }\n";
    echo "    if(nextBtn){\n";
    echo "      nextBtn.addEventListener('click', function(){\n";
    echo "        renderCalendar(shiftMonth(activeMonthValue, 1));\n";
    echo "      });\n";
    echo "    }\n";
    echo "    document.querySelectorAll('[data-dg-calendar-open]').forEach(function(trigger){\n";
    echo "      trigger.addEventListener('click', function(){\n";
    echo "        renderCalendar(activeMonthValue || (new Date().getFullYear() + '-' + String(new Date().getMonth() + 1).padStart(2, '0')));\n";
    echo "        calendarModalControl.open();\n";
    echo "      });\n";
    echo "    });\n";
    echo "  }\n";
    echo "  document.addEventListener('keydown', function(event){\n";
    echo "    if(event.key !== 'Escape'){ return; }\n";
    echo "    if(groupModal && groupModal.classList.contains('is-open')){ groupModalControl.close(); }\n";
    echo "    if(calendarModal && calendarModal.classList.contains('is-open')){ calendarModalControl.close(); }\n";
    echo "    if(monthModal && monthModal.classList.contains('is-open')){ monthModalControl.close(); }\n";
    echo "    if(dateModal && dateModal.classList.contains('is-open')){ dateModalControl.close(); }\n";
    echo "    if(photoModal && photoModal.classList.contains('is-open')){ photoModalControl.close(); }\n";
    echo "  });\n";
    echo "})();\n";
    echo "</script>\n";

    if ($renderAsTabPanel) {
        echo "</section>\n";
    } else {
        page_footer();
    }
}
