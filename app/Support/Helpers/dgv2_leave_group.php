<?php

function dgv2_leave_group(array &$model, string $personId, string $groupId): array {
    if ($personId === '') {
        return ['ok' => false, 'error' => 'invalid_person'];
    }
    if ($groupId === '') {
        return ['ok' => false, 'error' => 'missing_group'];
    }

    $personExists = false;
    foreach ($model['discipleship_persons'] as $person) {
        if (!is_array($person)) {
            continue;
        }
        if (trim((string) ($person['id'] ?? '')) !== $personId) {
            continue;
        }
        $personExists = true;
        break;
    }
    if (!$personExists) {
        return ['ok' => false, 'error' => 'invalid_person'];
    }

    foreach ($model['group_leaderships'] as $leadership) {
        if (!is_array($leadership) || !dgv2_is_current_period($leadership)) {
            continue;
        }
        if (trim((string) ($leadership['leader_person_id'] ?? '')) === $personId) {
            return ['ok' => false, 'error' => 'in_use'];
        }
    }

    $membershipFound = false;
    $now = now_iso();
    foreach ($model['group_memberships'] as &$membership) {
        if (!is_array($membership) || !dgv2_is_current_period($membership)) {
            continue;
        }
        if (trim((string) ($membership['person_id'] ?? '')) !== $personId) {
            continue;
        }
        if (trim((string) ($membership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $membership['end_date'] = today_date();
        $membership['status'] = 'closed';
        $membership['reason_end'] = 'left_group';
        $membership['updated_at'] = $now;
        $membershipFound = true;
        break;
    }
    unset($membership);

    if (!$membershipFound) {
        return ['ok' => false, 'error' => 'not_in_group'];
    }

    return ['ok' => true];
}
