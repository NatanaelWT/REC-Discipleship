<?php

function dgv2_archive_person(array &$model, string $personId): array {
    if ($personId === '') {
        return ['ok' => false, 'error' => 'invalid_person'];
    }
    foreach ($model['discipleship_persons'] as &$person) {
        if (!is_array($person) || trim((string) ($person['id'] ?? '')) !== $personId) {
            continue;
        }
        $person['status'] = 'inactive';
        $person['updated_at'] = now_iso();
    }
    unset($person);
    foreach ($model['group_memberships'] as &$membership) {
        if (!is_array($membership) || !dgv2_is_current_period($membership)) {
            continue;
        }
        if (trim((string) ($membership['person_id'] ?? '')) !== $personId) {
            continue;
        }
        $membership['end_date'] = today_date();
        $membership['status'] = 'closed';
        $membership['reason_end'] = 'person_archived';
        $membership['updated_at'] = now_iso();
    }
    unset($membership);
    foreach ($model['group_leaderships'] as &$leadership) {
        if (!is_array($leadership) || !dgv2_is_current_period($leadership)) {
            continue;
        }
        if (trim((string) ($leadership['leader_person_id'] ?? '')) !== $personId) {
            continue;
        }
        $leadership['end_date'] = today_date();
        $leadership['status'] = 'closed';
        $leadership['reason_change'] = 'person_archived';
        $leadership['updated_at'] = now_iso();
    }
    unset($leadership);
    return ['ok' => true];
}
