<?php

function pohon_dot_group_label(
    string $groupId,
    array $groupsById,
    array $leadershipsByGroup,
    array $membershipsByGroup,
    array $peopleById
): string {
    $group = $groupsById[$groupId] ?? [];
    $stage = pohon_dot_group_stage($group);
    if ($stage === '') {
        $stage = 'Tanpa stage';
    }

    $leaderNames = [];
    foreach ($leadershipsByGroup[$groupId] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $leaderId = trim((string) ($row['leader_person_id'] ?? ''));
        if ($leaderId === '') {
            continue;
        }
        $leaderNames[] = pohon_dot_person_name($peopleById, $leaderId);
    }
    $leaderNames = array_values(array_unique($leaderNames));
    sort($leaderNames, SORT_NATURAL | SORT_FLAG_CASE);

    $memberIds = [];
    foreach ($membershipsByGroup[$groupId] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $personId = trim((string) ($row['person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $memberIds[$personId] = true;
    }

    $lines = [$stage];
    if ($leaderNames !== []) {
        $lines[] = 'Leader: ' . implode(', ', $leaderNames);
    }
    $lines[] = count($memberIds) . ' anggota aktif';

    return implode("\n", $lines);
}
