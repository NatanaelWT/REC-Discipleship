<?php

function build_dg_public_form_data(array $groups, array $peopleById): array {
    $leadersById = [];
    $groupsById = [];

    foreach ($groups as $grp) {
        $groupId = trim((string) ($grp['id'] ?? ''));
        $leaderId = trim((string) ($grp['leader_id'] ?? ''));
        if ($groupId === '' || $leaderId === '') {
            continue;
        }

        $leaderName = '';
        if (isset($peopleById[$leaderId])) {
            $leaderName = trim((string) ($peopleById[$leaderId]['name'] ?? ''));
        }
        if ($leaderName === '') {
            continue;
        }

        if (!isset($leadersById[$leaderId])) {
            $leadersById[$leaderId] = [
                'id' => $leaderId,
                'name' => $leaderName,
            ];
        }

        $groupName = trim((string) ($grp['name'] ?? ''));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }

        $members = [];
        $memberIds = $grp['member_ids'] ?? [];
        if (!is_array($memberIds)) {
            $memberIds = [];
        }
        foreach ($memberIds as $memberId) {
            $memberId = trim((string) $memberId);
            if ($memberId === '') {
                continue;
            }
            $memberName = '';
            if (isset($peopleById[$memberId])) {
                $memberName = trim((string) ($peopleById[$memberId]['name'] ?? ''));
            }
            if ($memberName === '') {
                continue;
            }
            $members[] = [
                'id' => $memberId,
                'name' => $memberName,
            ];
        }
        usort($members, function ($a, $b) {
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $groupsById[$groupId] = [
            'id' => $groupId,
            'leader_id' => $leaderId,
            'leader_name' => $leaderName,
            'name' => $groupName,
            'progress' => trim((string) ($grp['progress'] ?? '')),
            'members' => $members,
        ];
    }

    $leaders = array_values($leadersById);
    usort($leaders, function ($a, $b) {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $groupsList = array_values($groupsById);
    usort($groupsList, function ($a, $b) {
        $leaderCmp = strcasecmp((string) ($a['leader_name'] ?? ''), (string) ($b['leader_name'] ?? ''));
        if ($leaderCmp !== 0) {
            return $leaderCmp;
        }
        $groupCmp = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        if ($groupCmp !== 0) {
            return $groupCmp;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $groupMap = [];
    foreach ($groupsList as $groupRow) {
        $gid = trim((string) ($groupRow['id'] ?? ''));
        if ($gid === '') {
            continue;
        }
        $groupMap[$gid] = $groupRow;
    }

    return [
        'leaders' => $leaders,
        'groups' => $groupsList,
        'group_map' => $groupMap,
    ];
}
