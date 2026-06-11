<?php

function pohon_dot_person_label(
    string $personId,
    array $peopleById,
    array $childrenByMentor,
    array $incomingMentorsByDisciple,
    array $leadershipsByPerson,
    array $membershipsByPerson,
    array $groupsById
): string {
    $lines = [pohon_dot_person_name($peopleById, $personId)];

    $roles = [];
    if (!empty($incomingMentorsByDisciple[$personId])) {
        $roles[] = 'Murid';
    }
    if (!empty($childrenByMentor[$personId])) {
        $roles[] = 'Mentor';
    }
    if (!empty($leadershipsByPerson[$personId])) {
        $roles[] = 'Leader DG';
    }
    if (!empty($membershipsByPerson[$personId])) {
        $roles[] = 'Anggota DG';
    }
    $roles = array_values(array_unique($roles));
    if ($roles !== []) {
        $lines[] = implode(' | ', $roles);
    }

    $stages = [];
    foreach ($leadershipsByPerson[$personId] ?? [] as $row) {
        $groupId = trim((string) ($row['group_id'] ?? ''));
        $stage = pohon_dot_group_stage($groupsById[$groupId] ?? []);
        $stages[] = $stage !== '' ? 'Lead ' . $stage : 'Lead DG';
    }
    foreach ($membershipsByPerson[$personId] ?? [] as $row) {
        $groupId = trim((string) ($row['group_id'] ?? ''));
        $stage = pohon_dot_group_stage($groupsById[$groupId] ?? []);
        $stages[] = $stage !== '' ? $stage : 'Anggota DG';
    }
    $stages = array_values(array_unique($stages));
    if ($stages !== []) {
        $lines[] = implode(', ', $stages);
    }

    return implode("\n", $lines);
}
