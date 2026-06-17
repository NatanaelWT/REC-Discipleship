<?php

function dgv2_save_group(array &$model, array $payload, array $peopleById): array {
    $id = trim((string) ($payload['id'] ?? ''));
    $leaderId = trim((string) ($payload['leader_id'] ?? ''));
    $assistantId = trim((string) ($payload['assistant_id'] ?? ''));
    $stage = normalize_dg_progress_value((string) ($payload['progress'] ?? ''));
    $notes = trim((string) ($payload['notes'] ?? ''));
    $parentGroupId = trim((string) ($payload['parent_group_id'] ?? ''));
    $memberIds = $payload['member_ids'] ?? [];
    if (!is_array($memberIds)) {
        $memberIds = [];
    }
    $memberIds = array_values(array_unique(array_filter(array_map('strval', $memberIds), static function (string $value): bool {
        return trim($value) !== '';
    })));

    if ($leaderId === '' || !isset($peopleById[$leaderId])) {
        return ['ok' => false, 'error' => 'invalid_group'];
    }
    if ($assistantId === $leaderId) {
        $assistantId = '';
    }
    if ($assistantId !== '' && !isset($peopleById[$assistantId])) {
        return ['ok' => false, 'error' => 'invalid_group'];
    }
    foreach ($memberIds as $personId) {
        if (!isset($peopleById[$personId])) {
            return ['ok' => false, 'error' => 'invalid_group'];
        }
    }
    if ($stage === '') {
        $stage = 'DG 1';
    }

    $parentGroup = null;
    if ($parentGroupId !== '') {
        foreach ($model['discipleship_groups'] as $group) {
            if (!is_array($group)) {
                continue;
            }
            if (trim((string) ($group['id'] ?? '')) !== $parentGroupId) {
                continue;
            }
            $parentGroup = $group;
            break;
        }
        if ($parentGroup === null) {
            return ['ok' => false, 'error' => 'invalid_group'];
        }

        $parentStatus = strtolower(trim((string) ($parentGroup['status'] ?? 'active')));
        if ($parentStatus !== 'active') {
            return ['ok' => false, 'error' => 'invalid_group'];
        }
        $parentMemberIds = in_array($parentStatus, ['completed', 'inactive', 'archived', 'closed', 'finished'], true)
            ? dgv2_group_historical_member_ids($model, $parentGroupId)
            : dgv2_group_active_member_ids($model, $parentGroupId);
        foreach ($memberIds as $personId) {
            if (!in_array($personId, $parentMemberIds, true)) {
                return ['ok' => false, 'error' => 'invalid_group'];
            }
        }
        $parentStage = normalize_dg_progress_value((string) ($parentGroup['current_stage'] ?? $parentGroup['start_stage'] ?? ''));
        if ($parentStage === 'DG 1') {
            $stage = 'DG 2';
        } elseif ($parentStage === 'DG 2') {
            $stage = 'DG 3';
        } else {
            return ['ok' => false, 'error' => 'invalid_group'];
        }
    }

    $groupIndex = null;
    $existing = null;
    foreach ($model['discipleship_groups'] as $index => $group) {
        if (!is_array($group)) {
            continue;
        }
        if ($id !== '' && trim((string) ($group['id'] ?? '')) === $id) {
            $groupIndex = $index;
            $existing = $group;
            break;
        }
    }

    $groupId = $id !== '' ? $id : generate_id('grp');
    $now = now_iso();
    $row = [
        'id' => $groupId,
        'status' => 'active',
        'start_stage' => normalize_dg_progress_value((string) ($existing['start_stage'] ?? $stage)) ?: $stage,
        'current_stage' => $stage,
        'parent_group_id' => $parentGroupId,
        'notes' => $notes,
        'created_at' => trim((string) ($existing['created_at'] ?? $now)) ?: $now,
        'updated_at' => $now,
    ];
    if ($groupIndex === null) {
        $model['discipleship_groups'][] = $row;
    } else {
        $model['discipleship_groups'][$groupIndex] = $row;
    }
    dgv2_sync_group_leaderships($model, $groupId, $leaderId, $assistantId);
    dgv2_sync_group_memberships($model, $groupId, $memberIds, $stage);

    if ($parentGroupId !== '') {
        if ($groupIndex === null) {
            foreach ($model['discipleship_groups'] as &$group) {
                if (!is_array($group) || trim((string) ($group['id'] ?? '')) !== $parentGroupId) {
                    continue;
                }
                $group['status'] = 'completed';
                $group['updated_at'] = $now;
            }
            unset($group);
            foreach ($model['group_memberships'] as &$membership) {
                if (!is_array($membership) || !dgv2_is_current_period($membership)) {
                    continue;
                }
                if (trim((string) ($membership['group_id'] ?? '')) !== $parentGroupId) {
                    continue;
                }
                $membership['end_date'] = today_date();
                $membership['status'] = 'closed';
                $membership['reason_end'] = 'continued_to_child_group';
                $membership['updated_at'] = $now;
            }
            unset($membership);
            foreach ($model['group_leaderships'] as &$leadership) {
                if (!is_array($leadership) || !dgv2_is_current_period($leadership)) {
                    continue;
                }
                if (trim((string) ($leadership['group_id'] ?? '')) !== $parentGroupId) {
                    continue;
                }
                $leadership['end_date'] = today_date();
                $leadership['status'] = 'closed';
                $leadership['reason_change'] = 'continued_to_child_group';
                $leadership['updated_at'] = $now;
            }
            unset($leadership);

            foreach ($memberIds as $personId) {
                dgv2_close_active_relation_for_disciple($model, $personId, 'continued_to_child_group');
                dgv2_open_relation($model, $leaderId, $personId, $groupId, $stage);
            }
        }

        $hasMultiplication = false;
        foreach ($model['group_multiplications'] as $multiplication) {
            if (!is_array($multiplication)) {
                continue;
            }
            if (trim((string) ($multiplication['new_group_id'] ?? '')) === $groupId) {
                $hasMultiplication = true;
                break;
            }
        }
        if (!$hasMultiplication) {
            $model['group_multiplications'][] = [
                'id' => generate_id('gmx'),
                'new_group_id' => $groupId,
                'source_group_id' => $parentGroupId,
                'initiated_by_person_id' => $leaderId,
                'start_date' => today_date(),
                'notes' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }
    return ['ok' => true, 'group_id' => $groupId];
}
