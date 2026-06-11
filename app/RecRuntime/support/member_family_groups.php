<?php

function member_family_groups(array $members): array {
    $membersSorted = array_values($members);
    usort($membersSorted, function ($a, $b) {
        $nameA = trim((string) ($a['full_name'] ?? ''));
        $nameB = trim((string) ($b['full_name'] ?? ''));
        $cmpName = strcasecmp($nameA, $nameB);
        if ($cmpName !== 0) {
            return $cmpName;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $membersById = index_by_id($membersSorted);
    $adjacency = [];
    foreach ($membersSorted as $member) {
        $memberId = trim((string) ($member['id'] ?? ''));
        if ($memberId === '') {
            continue;
        }
        $adjacency[$memberId] = [];
    }

    foreach ($membersSorted as $member) {
        $memberId = trim((string) ($member['id'] ?? ''));
        if ($memberId === '' || !isset($adjacency[$memberId])) {
            continue;
        }
        $familyIds = $member['family_ids'] ?? [];
        if (!is_array($familyIds)) {
            continue;
        }
        foreach ($familyIds as $familyId) {
            $familyId = trim((string) $familyId);
            if ($familyId === '' || $familyId === $memberId || !isset($adjacency[$familyId])) {
                continue;
            }
            $adjacency[$memberId][$familyId] = true;
            $adjacency[$familyId][$memberId] = true;
        }
    }

    $visited = [];
    $groups = [];
    foreach (array_keys($adjacency) as $startId) {
        if (isset($visited[$startId])) {
            continue;
        }

        $stack = [$startId];
        $componentIds = [];
        while (count($stack) > 0) {
            $currentId = array_pop($stack);
            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;
            $componentIds[] = $currentId;

            $neighbors = array_keys($adjacency[$currentId] ?? []);
            foreach ($neighbors as $neighborId) {
                if (!isset($visited[$neighborId])) {
                    $stack[] = $neighborId;
                }
            }
        }

        $groupMembers = [];
        foreach ($componentIds as $memberId) {
            if (!isset($membersById[$memberId])) {
                continue;
            }
            $groupMembers[] = $membersById[$memberId];
        }
        if (count($groupMembers) === 0) {
            continue;
        }
        usort($groupMembers, function ($a, $b) {
            $nameA = trim((string) ($a['full_name'] ?? ''));
            $nameB = trim((string) ($b['full_name'] ?? ''));
            $cmpName = strcasecmp($nameA, $nameB);
            if ($cmpName !== 0) {
                return $cmpName;
            }
            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        $displayName = trim((string) ($groupMembers[0]['full_name'] ?? ''));
        if ($displayName === '') {
            $displayName = 'Tanpa Nama';
        }

        $groups[] = [
            'display_name' => $displayName,
            'member_count' => count($groupMembers),
            'members' => $groupMembers,
        ];
    }

    usort($groups, function ($a, $b) {
        $aCount = max(0, (int) ($a['member_count'] ?? 0));
        $bCount = max(0, (int) ($b['member_count'] ?? 0));
        $aHasFamily = $aCount > 1 ? 1 : 0;
        $bHasFamily = $bCount > 1 ? 1 : 0;
        if ($aHasFamily !== $bHasFamily) {
            return $bHasFamily <=> $aHasFamily;
        }

        if ($aCount !== $bCount) {
            return $bCount <=> $aCount;
        }

        $cmpName = strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
        if ($cmpName !== 0) {
            return $cmpName;
        }
        return strcmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
    });

    return $groups;
}
