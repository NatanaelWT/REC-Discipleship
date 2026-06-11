<?php

function hydrate_dg_meeting_reports_for_runtime(array $reports, array $groupsById, array $peopleById): array {
    $hydratedReports = [];
    foreach ($reports as $report) {
        if (!is_array($report)) {
            continue;
        }

        $groupId = trim((string) ($report['group_id'] ?? ''));
        $leaderId = trim((string) ($report['leader_id'] ?? ''));
        $groupRow = ($groupId !== '' && isset($groupsById[$groupId]) && is_array($groupsById[$groupId])) ? $groupsById[$groupId] : null;

        $leaderName = trim((string) ($report['leader_name'] ?? ''));
        if ($leaderName === '' && $leaderId !== '' && isset($peopleById[$leaderId])) {
            $leaderName = trim((string) ($peopleById[$leaderId]['name'] ?? ''));
        }
        if ($leaderName === '' && is_array($groupRow)) {
            $groupLeaderId = trim((string) ($groupRow['leader_id'] ?? ''));
            if ($groupLeaderId !== '' && isset($peopleById[$groupLeaderId])) {
                $leaderName = trim((string) ($peopleById[$groupLeaderId]['name'] ?? ''));
            }
        }

        $groupName = trim((string) ($report['group_name'] ?? ''));
        if ($groupName === '' && is_array($groupRow)) {
            $groupName = trim((string) ($groupRow['name'] ?? ''));
        }
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }

        $groupMemberNameById = [];
        if (is_array($groupRow)) {
            $groupMemberIds = $groupRow['member_ids'] ?? [];
            if (!is_array($groupMemberIds)) {
                $groupMemberIds = [];
            }
            $groupMemberFallbackMap = normalize_group_member_names($groupRow['member_names'] ?? []);
            foreach ($groupMemberIds as $memberIdRaw) {
                $memberId = trim((string) $memberIdRaw);
                if ($memberId === '') {
                    continue;
                }
                if (isset($peopleById[$memberId])) {
                    $resolved = trim((string) ($peopleById[$memberId]['name'] ?? ''));
                    if ($resolved !== '') {
                        $groupMemberNameById[$memberId] = $resolved;
                        continue;
                    }
                }
                if (isset($groupMemberFallbackMap[$memberId])) {
                    $resolved = trim((string) $groupMemberFallbackMap[$memberId]);
                    if ($resolved !== '') {
                        $groupMemberNameById[$memberId] = $resolved;
                    }
                }
            }
        }

        $absentMemberIds = $report['absent_member_ids'] ?? [];
        if (!is_array($absentMemberIds)) {
            $absentMemberIds = [];
        }
        $absentMemberNames = [];
        foreach ($absentMemberIds as $memberIdRaw) {
            $memberId = trim((string) $memberIdRaw);
            if ($memberId === '') {
                continue;
            }
            $resolvedName = '';
            if (isset($peopleById[$memberId])) {
                $resolvedName = trim((string) ($peopleById[$memberId]['name'] ?? ''));
            }
            if ($resolvedName === '' && isset($groupMemberNameById[$memberId])) {
                $resolvedName = $groupMemberNameById[$memberId];
            }
            if ($resolvedName !== '' && !in_array($resolvedName, $absentMemberNames, true)) {
                $absentMemberNames[] = $resolvedName;
            }
        }
        if (count($absentMemberNames) === 0 && isset($report['absent_member_names']) && is_array($report['absent_member_names'])) {
            foreach ($report['absent_member_names'] as $fallbackNameRaw) {
                $fallbackName = trim((string) $fallbackNameRaw);
                if ($fallbackName !== '' && !in_array($fallbackName, $absentMemberNames, true)) {
                    $absentMemberNames[] = $fallbackName;
                }
            }
        }

        $meditationSharerIds = $report['meditation_sharer_ids'] ?? [];
        if (!is_array($meditationSharerIds)) {
            $meditationSharerIds = [];
        }
        $meditationSharerNames = [];
        foreach ($meditationSharerIds as $memberIdRaw) {
            $memberId = trim((string) $memberIdRaw);
            if ($memberId === '') {
                continue;
            }
            $resolvedName = '';
            if (isset($peopleById[$memberId])) {
                $resolvedName = trim((string) ($peopleById[$memberId]['name'] ?? ''));
            }
            if ($resolvedName === '' && isset($groupMemberNameById[$memberId])) {
                $resolvedName = $groupMemberNameById[$memberId];
            }
            if ($resolvedName !== '' && !in_array($resolvedName, $meditationSharerNames, true)) {
                $meditationSharerNames[] = $resolvedName;
            }
        }
        if (count($meditationSharerNames) === 0 && isset($report['meditation_sharer_names']) && is_array($report['meditation_sharer_names'])) {
            foreach ($report['meditation_sharer_names'] as $fallbackNameRaw) {
                $fallbackName = trim((string) $fallbackNameRaw);
                if ($fallbackName !== '' && !in_array($fallbackName, $meditationSharerNames, true)) {
                    $meditationSharerNames[] = $fallbackName;
                }
            }
        }

        $report['leader_name'] = $leaderName;
        $report['group_name'] = $groupName;
        $report['absent_member_ids'] = array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $absentMemberIds), function ($value) {
            return $value !== '';
        })));
        $report['meditation_sharer_ids'] = array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $meditationSharerIds), function ($value) {
            return $value !== '';
        })));
        $report['absent_member_names'] = $absentMemberNames;
        $report['meditation_sharer_names'] = $meditationSharerNames;

        $hydratedReports[] = $report;
    }
    return array_values($hydratedReports);
}
