<?php

function dgv2_validation_errors(array $model, array $members, array $mskClasses): array {
    $errors = [];
    $identityById = [];
    foreach (dgv2_identity_sources($members, $mskClasses) as $row) {
        $identityById[(string) ($row['id'] ?? '')] = $row;
    }
    $personIds = [];
    foreach ($model['discipleship_persons'] as $person) {
        if (!is_array($person)) {
            continue;
        }
        $personId = trim((string) ($person['id'] ?? ''));
        $memberId = trim((string) ($person['member_id'] ?? ''));
        if ($personId === '' || $memberId === '') {
            $errors[] = 'Person tanpa id/member_id lengkap.';
            continue;
        }
        $personIds[$personId] = true;
        $identity = dgv2_find_identity($identityById, $memberId);
        if (strtolower(trim((string) ($person['status'] ?? 'active'))) === 'active' && empty($identity['completed_msk'])) {
            $errors[] = 'Peserta aktif tanpa MSK selesai: ' . $personId;
        }
    }
    $activeDisciples = [];
    foreach ($model['discipleship_relations'] as $relation) {
        if (!is_array($relation) || !dgv2_is_current_period($relation)) {
            continue;
        }
        $mentorId = trim((string) ($relation['mentor_person_id'] ?? ''));
        $discipleId = trim((string) ($relation['disciple_person_id'] ?? ''));
        if (!isset($personIds[$mentorId]) || !isset($personIds[$discipleId])) {
            $errors[] = 'Relasi aktif mengacu ke person yang tidak ada.';
        }
        if (isset($activeDisciples[$discipleId])) {
            $errors[] = 'Disciple aktif ganda: ' . $discipleId;
        }
        $activeDisciples[$discipleId] = true;
    }
    $groupIds = [];
    foreach ($model['discipleship_groups'] as $group) {
        if (!is_array($group)) {
            continue;
        }
        $groupId = trim((string) ($group['id'] ?? ''));
        if ($groupId !== '') {
            $groupIds[$groupId] = true;
        }
    }
    foreach ($model['group_memberships'] as $membership) {
        if (!is_array($membership) || !dgv2_is_current_period($membership)) {
            continue;
        }
        if (!isset($groupIds[trim((string) ($membership['group_id'] ?? ''))]) || !isset($personIds[trim((string) ($membership['person_id'] ?? ''))])) {
            $errors[] = 'Membership aktif referensinya tidak valid.';
        }
    }
    foreach ($model['group_leaderships'] as $leadership) {
        if (!is_array($leadership) || !dgv2_is_current_period($leadership)) {
            continue;
        }
        if (!isset($groupIds[trim((string) ($leadership['group_id'] ?? ''))]) || !isset($personIds[trim((string) ($leadership['leader_person_id'] ?? ''))])) {
            $errors[] = 'Leadership aktif referensinya tidak valid.';
        }
    }
    return $errors;
}
