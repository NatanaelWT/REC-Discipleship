<?php

function dgv2_reactivate_group(array &$model, string $groupId): array {
    if ($groupId === '') {
        return ['ok' => false, 'error' => 'invalid_group'];
    }

    $groupIndex = null;
    $groupStage = '';
    foreach ($model['discipleship_groups'] as $index => $group) {
        if (!is_array($group) || trim((string) ($group['id'] ?? '')) !== $groupId) {
            continue;
        }
        $groupIndex = $index;
        $groupStatus = strtolower(trim((string) ($group['status'] ?? 'active')));
        if ($groupStatus !== 'completed') {
            return ['ok' => false, 'error' => 'group_not_completed'];
        }
        $groupStage = normalize_dg_progress_value((string) ($group['current_stage'] ?? $group['start_stage'] ?? ''));
        break;
    }
    if ($groupIndex === null) {
        return ['ok' => false, 'error' => 'invalid_group'];
    }

    $now = now_iso();
    $model['discipleship_groups'][$groupIndex]['status'] = 'active';
    $model['discipleship_groups'][$groupIndex]['updated_at'] = $now;

    $leaderId = '';
    $assistantId = '';
    $latestLeaderSort = '';
    $latestAssistantSort = '';
    $leaderRestoreIndex = null;
    $assistantRestoreIndex = null;
    foreach ($model['group_leaderships'] as $leadership) {
        if (!is_array($leadership) || trim((string) ($leadership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $personId = trim((string) ($leadership['leader_person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $role = strtolower(trim((string) ($leadership['role'] ?? 'leader')));
        $sortDate = trim((string) ($leadership['end_date'] ?? $leadership['start_date'] ?? $leadership['updated_at'] ?? ''));
        if ($role === 'co_leader' || $role === 'assistant') {
            if ($assistantId === '' || strcmp($sortDate, $latestAssistantSort) > 0) {
                $assistantId = $personId;
                $latestAssistantSort = $sortDate;
            }
        } else {
            if ($leaderId === '' || strcmp($sortDate, $latestLeaderSort) > 0) {
                $leaderId = $personId;
                $latestLeaderSort = $sortDate;
            }
        }
    }
    foreach ($model['group_leaderships'] as $index => $leadership) {
        if (!is_array($leadership) || trim((string) ($leadership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $personId = trim((string) ($leadership['leader_person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $reasonChange = trim((string) ($leadership['reason_change'] ?? ''));
        if ($reasonChange !== 'group_completed') {
            continue;
        }
        $role = strtolower(trim((string) ($leadership['role'] ?? 'leader')));
        $sortDate = trim((string) ($leadership['end_date'] ?? $leadership['start_date'] ?? $leadership['updated_at'] ?? ''));
        if (($role === 'co_leader' || $role === 'assistant') && $personId === $assistantId) {
            if ($assistantRestoreIndex === null || strcmp($sortDate, $latestAssistantSort) >= 0) {
                $assistantRestoreIndex = $index;
            }
            continue;
        }
        if ($role !== 'co_leader' && $role !== 'assistant' && $personId === $leaderId) {
            if ($leaderRestoreIndex === null || strcmp($sortDate, $latestLeaderSort) >= 0) {
                $leaderRestoreIndex = $index;
            }
        }
    }
    if ($leaderRestoreIndex !== null) {
        $model['group_leaderships'][$leaderRestoreIndex]['end_date'] = '';
        $model['group_leaderships'][$leaderRestoreIndex]['status'] = 'active';
        $model['group_leaderships'][$leaderRestoreIndex]['reason_change'] = '';
        $model['group_leaderships'][$leaderRestoreIndex]['updated_at'] = $now;
    }
    if ($assistantRestoreIndex !== null) {
        $model['group_leaderships'][$assistantRestoreIndex]['end_date'] = '';
        $model['group_leaderships'][$assistantRestoreIndex]['status'] = 'active';
        $model['group_leaderships'][$assistantRestoreIndex]['reason_change'] = '';
        $model['group_leaderships'][$assistantRestoreIndex]['updated_at'] = $now;
    }
    dgv2_sync_group_leaderships($model, $groupId, $leaderId, $assistantId);

    $restoreMemberIds = [];
    $restoreMembershipIndexes = [];
    $restoreMembershipSorts = [];
    foreach ($model['group_memberships'] as $index => $membership) {
        if (!is_array($membership) || trim((string) ($membership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $personId = trim((string) ($membership['person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $reasonEnd = trim((string) ($membership['reason_end'] ?? ''));
        if ($reasonEnd !== 'group_completed') {
            continue;
        }
        if (count(dgv2_person_active_group_ids($model, $personId, [$groupId])) > 0) {
            continue;
        }
        $sortDate = trim((string) ($membership['end_date'] ?? $membership['start_date'] ?? $membership['updated_at'] ?? ''));
        if (!isset($restoreMembershipIndexes[$personId]) || strcmp($sortDate, (string) ($restoreMembershipSorts[$personId] ?? '')) >= 0) {
            $restoreMembershipIndexes[$personId] = $index;
            $restoreMembershipSorts[$personId] = $sortDate;
        }
    }
    foreach ($restoreMembershipIndexes as $personId => $membershipIndex) {
        $model['group_memberships'][$membershipIndex]['end_date'] = '';
        $model['group_memberships'][$membershipIndex]['status'] = 'active';
        $model['group_memberships'][$membershipIndex]['reason_end'] = '';
        if ($groupStage !== '') {
            $model['group_memberships'][$membershipIndex]['stage'] = $groupStage;
        }
        $model['group_memberships'][$membershipIndex]['updated_at'] = $now;
        $restoreMemberIds[] = $personId;
    }
    dgv2_sync_group_memberships($model, $groupId, $restoreMemberIds, $groupStage !== '' ? $groupStage : 'DG 1');

    foreach ($restoreMemberIds as $personId) {
        dgv2_close_active_relation_for_disciple($model, $personId, 'reactivated_group');
        if ($leaderId !== '') {
            dgv2_open_relation($model, $leaderId, $personId, $groupId, $groupStage);
        }
    }

    return ['ok' => true];
}
