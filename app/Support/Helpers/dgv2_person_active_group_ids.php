<?php

function dgv2_person_active_group_ids(array $model, string $personId, array $excludeGroupIds = []): array {
    $rows = [];
    if ($personId === '') {
        return $rows;
    }
    $excluded = array_fill_keys(array_filter(array_map('strval', $excludeGroupIds), static fn($value) => $value !== ''), true);
    foreach ($model['group_memberships'] as $membership) {
        if (!is_array($membership) || !dgv2_is_current_period($membership)) {
            continue;
        }
        if (trim((string) ($membership['person_id'] ?? '')) !== $personId) {
            continue;
        }
        $groupId = trim((string) ($membership['group_id'] ?? ''));
        if ($groupId === '' || isset($excluded[$groupId])) {
            continue;
        }
        if (!in_array($groupId, $rows, true)) {
            $rows[] = $groupId;
        }
    }
    return $rows;
}
