<?php

function dgv2_archive_group(array &$model, string $groupId): array {
    if ($groupId === '') {
        return ['ok' => false, 'error' => 'invalid_group'];
    }
    foreach ($model['discipleship_groups'] as &$group) {
        if (!is_array($group) || trim((string) ($group['id'] ?? '')) !== $groupId) {
            continue;
        }
        $group['status'] = 'archived';
        $group['updated_at'] = now_iso();
    }
    unset($group);
    foreach ($model['group_memberships'] as &$membership) {
        if (!is_array($membership) || !dgv2_is_current_period($membership)) {
            continue;
        }
        if (trim((string) ($membership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $membership['end_date'] = today_date();
        $membership['status'] = 'closed';
        $membership['reason_end'] = 'group_archived';
        $membership['updated_at'] = now_iso();
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
        $leadership['reason_change'] = 'group_archived';
        $leadership['updated_at'] = now_iso();
    }
    unset($leadership);
    return ['ok' => true];
}
