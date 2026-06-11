<?php

function dgv2_groups_projection(array $model, array $peopleById): array {
    $rows = [];
    foreach ($model['discipleship_groups'] as $group) {
        if (!is_array($group)) {
            continue;
        }
        $groupId = trim((string) ($group['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        if (strtolower(trim((string) ($group['status'] ?? 'active'))) !== 'active') {
            continue;
        }
        $leaderId = '';
        $assistantId = '';
        foreach ($model['group_leaderships'] as $leadership) {
            if (!is_array($leadership) || !dgv2_is_current_period($leadership)) {
                continue;
            }
            if (trim((string) ($leadership['group_id'] ?? '')) !== $groupId) {
                continue;
            }
            $personId = trim((string) ($leadership['leader_person_id'] ?? ''));
            $role = strtolower(trim((string) ($leadership['role'] ?? 'leader')));
            if (($role === 'co_leader' || $role === 'assistant') && $assistantId === '') {
                $assistantId = $personId;
            } elseif ($leaderId === '') {
                $leaderId = $personId;
            }
        }
        if ($leaderId === '') {
            continue;
        }
        $memberIds = [];
        foreach ($model['group_memberships'] as $membership) {
            if (!is_array($membership) || !dgv2_is_current_period($membership)) {
                continue;
            }
            if (trim((string) ($membership['group_id'] ?? '')) !== $groupId) {
                continue;
            }
            $personId = trim((string) ($membership['person_id'] ?? ''));
            if ($personId !== '' && isset($peopleById[$personId]) && !in_array($personId, $memberIds, true)) {
                $memberIds[] = $personId;
            }
        }
        $progress = normalize_dg_progress_value((string) ($group['current_stage'] ?? $group['start_stage'] ?? ''));
        if ($progress === '') {
            $progress = 'DG 1';
        }
        $rows[] = [
            'id' => $groupId,
            'leader_id' => $leaderId,
            'assistant_id' => $assistantId,
            'name' => trim((string) ($group['name'] ?? 'Kelompok')) ?: 'Kelompok',
            'member_ids' => $memberIds,
            'leader_name' => trim((string) ($peopleById[$leaderId]['name'] ?? '')),
            'member_names' => build_group_member_names($memberIds, $peopleById),
            'progress' => $progress,
            'start_progress' => normalize_dg_progress_value((string) ($group['start_stage'] ?? '')),
            'parent_group_id' => trim((string) ($group['parent_group_id'] ?? '')),
            'notes' => trim((string) ($group['notes'] ?? '')),
            'created_at' => trim((string) ($group['created_at'] ?? now_iso())) ?: now_iso(),
            'updated_at' => trim((string) ($group['updated_at'] ?? now_iso())) ?: now_iso(),
        ];
    }
    return $rows;
}
