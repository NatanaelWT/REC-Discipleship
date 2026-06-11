<?php

function dgv2_group_active_member_ids(array $model, string $groupId): array {
    $rows = [];
    foreach ($model['group_memberships'] as $membership) {
        if (!is_array($membership) || !dgv2_is_current_period($membership)) {
            continue;
        }
        if (trim((string) ($membership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $personId = trim((string) ($membership['person_id'] ?? ''));
        if ($personId !== '' && !in_array($personId, $rows, true)) {
            $rows[] = $personId;
        }
    }
    return $rows;
}
