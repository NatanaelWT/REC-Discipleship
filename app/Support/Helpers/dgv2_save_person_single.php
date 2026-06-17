<?php

function dgv2_save_person_single(array &$model, array $payload, array $members, array $mskClasses): array {
    $identityById = [];
    foreach (dgv2_identity_sources($members, $mskClasses) as $row) {
        $identityById[(string) ($row['id'] ?? '')] = $row;
    }
    $id = trim((string) ($payload['id'] ?? ''));
    $memberId = trim((string) ($payload['member_id'] ?? ''));
    $leaderId = trim((string) ($payload['leader_id'] ?? ''));
    $groupId = trim((string) ($payload['group_id'] ?? ''));
    $notes = trim((string) ($payload['notes'] ?? ''));

    if ($leaderId === '' && $groupId !== '') {
        foreach ($model['group_leaderships'] as $leadership) {
            if (!is_array($leadership) || !dgv2_is_current_period($leadership)) {
                continue;
            }
            if (trim((string) ($leadership['group_id'] ?? '')) !== $groupId) {
                continue;
            }
            $role = strtolower(trim((string) ($leadership['role'] ?? 'leader')));
            if ($role === 'leader') {
                $leaderId = trim((string) ($leadership['leader_person_id'] ?? ''));
                if ($leaderId !== '') {
                    break;
                }
            }
        }
    }

    $personIndex = null;
    $person = null;
    foreach ($model['discipleship_persons'] as $index => $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($id !== '' && trim((string) ($row['id'] ?? '')) === $id) {
            $personIndex = $index;
            $person = $row;
            break;
        }
    }
    if ($person !== null) {
        $memberId = trim((string) ($person['member_id'] ?? $memberId));
    }
    $canonicalMemberId = dgv2_canonical_identity_source_id($memberId, $mskClasses);
    $identity = dgv2_find_identity($identityById, $canonicalMemberId !== '' ? $canonicalMemberId : $memberId);
    if ($canonicalMemberId === '' || empty($identity['completed_msk'])) {
        return ['ok' => false, 'error' => 'member_not_complete'];
    }
    $memberId = $canonicalMemberId;

    foreach ($model['discipleship_persons'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rowId = trim((string) ($row['id'] ?? ''));
        $rowMemberId = trim((string) ($row['member_id'] ?? ''));
        $rowCanonicalMemberId = dgv2_canonical_identity_source_id($rowMemberId, $mskClasses);
        $rowStatus = strtolower(trim((string) ($row['status'] ?? 'active')));
        if ($rowCanonicalMemberId === $memberId && $rowId !== $id && $rowStatus === 'active') {
            return ['ok' => false, 'error' => 'member_exists'];
        }
    }

    $personId = $id !== '' ? $id : generate_id('person');
    $now = now_iso();
    $row = [
        'id' => $personId,
        'member_id' => $memberId,
        'full_name' => trim((string) ($identity['full_name'] ?? '')),
        'phone' => trim((string) ($identity['whatsapp'] ?? '')),
        'gender' => trim((string) ($identity['gender'] ?? '')),
        'status' => 'active',
        'notes' => $notes,
        'kampus' => trim((string) ($person['kampus'] ?? '')),
        'jurusan' => trim((string) ($person['jurusan'] ?? '')),
        'pekerjaan' => trim((string) ($person['pekerjaan'] ?? '')),
        'created_at' => trim((string) ($person['created_at'] ?? $now)) ?: $now,
        'updated_at' => $now,
    ];
    if ($row['full_name'] === '') {
        return ['ok' => false, 'error' => 'invalid_member'];
    }
    if ($personIndex === null) {
        $model['discipleship_persons'][] = $row;
    } else {
        $model['discipleship_persons'][$personIndex] = $row;
    }

    dgv2_close_active_relation_for_disciple($model, $personId);
    foreach ($model['group_memberships'] as &$membership) {
        if (!is_array($membership) || !dgv2_is_current_period($membership)) {
            continue;
        }
        if (trim((string) ($membership['person_id'] ?? '')) !== $personId) {
            continue;
        }
        $membershipGroupId = trim((string) ($membership['group_id'] ?? ''));
        if ($groupId !== '' && $membershipGroupId === $groupId) {
            continue;
        }
        $membership['end_date'] = today_date();
        $membership['status'] = 'closed';
        $membership['reason_end'] = $groupId !== '' ? 'moved_group' : 'removed_from_group';
        $membership['updated_at'] = $now;
    }
    unset($membership);
    if ($leaderId !== '') {
        $groupStage = '';
        if ($groupId !== '') {
            foreach ($model['discipleship_groups'] as $group) {
                if (!is_array($group) || trim((string) ($group['id'] ?? '')) !== $groupId) {
                    continue;
                }
                $groupStage = normalize_dg_progress_value((string) ($group['current_stage'] ?? $group['start_stage'] ?? ''));
                break;
            }
        }
        dgv2_open_relation($model, $leaderId, $personId, $groupId, $groupStage);
    }
    if ($groupId !== '') {
        $groupStage = 'DG 1';
        foreach ($model['discipleship_groups'] as $group) {
            if (!is_array($group) || trim((string) ($group['id'] ?? '')) !== $groupId) {
                continue;
            }
            $groupStage = normalize_dg_progress_value((string) ($group['current_stage'] ?? $group['start_stage'] ?? ''));
            if ($groupStage === '') {
                $groupStage = 'DG 1';
            }
            break;
        }
        dgv2_sync_group_memberships($model, $groupId, array_values(array_unique(array_merge([$personId], dgv2_group_active_member_ids($model, $groupId)))), $groupStage);
    }
    return ['ok' => true, 'person_id' => $personId];
}
