<?php

function pohon_dot_primary_group_leader_name(string $groupId, array $leadershipsByGroup, array $peopleById): string {
    $leaders = [];
    foreach ($leadershipsByGroup[$groupId] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $personId = trim((string) ($row['leader_person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $leaders[] = pohon_dot_person_name($peopleById, $personId);
    }

    $leaders = array_values(array_unique($leaders));
    sort($leaders, SORT_NATURAL | SORT_FLAG_CASE);

    return $leaders[0] ?? '';
}
