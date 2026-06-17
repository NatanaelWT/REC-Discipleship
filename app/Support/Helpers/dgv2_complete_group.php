<?php

function dgv2_complete_group(array &$model, string $groupId): array {
    if ($groupId === '') {
        return ['ok' => false, 'error' => 'invalid_group'];
    }

    $groupFound = false;
    $groupStatus = 'active';
    foreach ($model['discipleship_groups'] as &$group) {
        if (!is_array($group) || trim((string) ($group['id'] ?? '')) !== $groupId) {
            continue;
        }
        $groupFound = true;
        $groupStatus = strtolower(trim((string) ($group['status'] ?? 'active')));
        if ($groupStatus !== 'active') {
            unset($group);
            return ['ok' => false, 'error' => 'group_not_active'];
        }
        $group['status'] = 'completed';
        $group['updated_at'] = now_iso();
        break;
    }
    unset($group);

    if (!$groupFound) {
        return ['ok' => false, 'error' => 'invalid_group'];
    }

    $now = now_iso();
    $groupMemberIds = [];
    foreach ($model['group_memberships'] as &$membership) {
        if (!is_array($membership) || !dgv2_is_current_period($membership)) {
            continue;
        }
        if (trim((string) ($membership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $personId = trim((string) ($membership['person_id'] ?? ''));
        if ($personId !== '') {
            $groupMemberIds[$personId] = true;
        }
        $membership['end_date'] = today_date();
        $membership['status'] = 'closed';
        $membership['reason_end'] = 'group_completed';
        $membership['updated_at'] = $now;
    }
    unset($membership);

    foreach ($model['group_leaderships'] as &$leadership) {
        if (!is_array($leadership) || !dgv2_is_current_period($leadership)) {
            continue;
        }
        if (trim((string) ($leadership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $leadership['end_date'] = today_date();
        $leadership['status'] = 'closed';
        $leadership['reason_change'] = 'group_completed';
        $leadership['updated_at'] = $now;
    }
    unset($leadership);

    foreach (array_keys($groupMemberIds) as $personId) {
        dgv2_close_active_relation_for_disciple($model, $personId, 'group_completed');
    }

    return ['ok' => true];
}
