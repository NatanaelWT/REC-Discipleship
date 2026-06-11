<?php

function dgv2_migrate_from_legacy(string $branch, array $legacyPeople, array $legacyGroups, array $legacyReports, array $members, array $mskClasses): array {
    $identityById = [];
    foreach (dgv2_identity_sources($members, $mskClasses) as $row) {
        $identityById[(string) ($row['id'] ?? '')] = $row;
    }
    $model = dgv2_empty_model();

    foreach ($legacyPeople as $row) {
        if (!is_array($row)) {
            continue;
        }
        $personId = trim((string) ($row['id'] ?? ''));
        $memberId = trim((string) ($row['member_id'] ?? ''));
        if ($personId === '' || $memberId === '') {
            continue;
        }
        $identity = dgv2_find_identity($identityById, $memberId);
        $createdAt = trim((string) ($row['created_at'] ?? now_iso())) ?: now_iso();
        $updatedAt = trim((string) ($row['updated_at'] ?? $createdAt)) ?: $createdAt;
        $model['discipleship_persons'][] = [
            'id' => $personId,
            'member_id' => $memberId,
            'full_name' => trim((string) ($identity['full_name'] ?? $row['name'] ?? '')),
            'phone' => trim((string) ($identity['whatsapp'] ?? $row['phone'] ?? '')),
            'gender' => trim((string) ($identity['gender'] ?? '')),
            'status' => 'active',
            'notes' => trim((string) ($row['notes'] ?? '')),
            'kampus' => trim((string) ($row['kampus'] ?? '')),
            'jurusan' => trim((string) ($row['jurusan'] ?? '')),
            'pekerjaan' => trim((string) ($row['pekerjaan'] ?? '')),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        $parentIds = [];
        if (isset($row['parent_ids']) && is_array($row['parent_ids'])) {
            $parentIds = $row['parent_ids'];
        } elseif (isset($row['parent_id'])) {
            $parentIds = [$row['parent_id']];
        }
        $firstParentId = trim((string) ($parentIds[0] ?? ''));
        if ($firstParentId !== '') {
            $model['discipleship_relations'][] = [
                'id' => generate_id('dsr'),
                'mentor_person_id' => $firstParentId,
                'disciple_person_id' => $personId,
                'context_group_id' => '',
                'stage_at_start' => '',
                'relation_type' => 'memuridkan_langsung',
                'start_date' => normalize_ymd_date($createdAt) ?: today_date(),
                'end_date' => '',
                'status' => 'active',
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }
    }

    foreach ($legacyGroups as $row) {
        if (!is_array($row)) {
            continue;
        }
        $groupId = trim((string) ($row['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $stage = normalize_dg_progress_value((string) ($row['progress'] ?? ''));
        if ($stage === '') {
            $stage = 'DG 1';
        }
        $createdAt = trim((string) ($row['created_at'] ?? now_iso())) ?: now_iso();
        $updatedAt = trim((string) ($row['updated_at'] ?? $createdAt)) ?: $createdAt;
        $model['discipleship_groups'][] = [
            'id' => $groupId,
            'status' => 'active',
            'start_stage' => $stage,
            'current_stage' => $stage,
            'parent_group_id' => '',
            'notes' => trim((string) ($row['notes'] ?? '')),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        $leaderId = trim((string) ($row['leader_id'] ?? ''));
        $assistantId = trim((string) ($row['assistant_id'] ?? ''));
        if ($leaderId !== '') {
            $model['group_leaderships'][] = [
                'id' => generate_id('gld'),
                'group_id' => $groupId,
                'leader_person_id' => $leaderId,
                'role' => 'leader',
                'start_date' => normalize_ymd_date($createdAt) ?: today_date(),
                'end_date' => '',
                'status' => 'active',
                'reason_change' => 'migrated_from_legacy',
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }
        if ($assistantId !== '' && $assistantId !== $leaderId) {
            $model['group_leaderships'][] = [
                'id' => generate_id('gld'),
                'group_id' => $groupId,
                'leader_person_id' => $assistantId,
                'role' => 'co_leader',
                'start_date' => normalize_ymd_date($createdAt) ?: today_date(),
                'end_date' => '',
                'status' => 'active',
                'reason_change' => 'migrated_from_legacy',
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        $memberIds = $row['member_ids'] ?? [];
        if (!is_array($memberIds)) {
            $memberIds = [];
        }
        foreach ($memberIds as $personIdRaw) {
            $personId = trim((string) $personIdRaw);
            if ($personId === '') {
                continue;
            }
            $model['group_memberships'][] = [
                'id' => generate_id('gmb'),
                'person_id' => $personId,
                'group_id' => $groupId,
                'role' => 'member',
                'stage' => $stage,
                'start_date' => normalize_ymd_date($createdAt) ?: today_date(),
                'end_date' => '',
                'status' => 'active',
                'reason_end' => '',
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }
    }

    return dgv2_normalize_model($model);
}
