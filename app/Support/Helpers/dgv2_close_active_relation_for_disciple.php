<?php

function dgv2_close_active_relation_for_disciple(array &$model, string $disciplePersonId, string $reason = 'changed_mentor'): void {
    foreach ($model['discipleship_relations'] as &$relation) {
        if (!is_array($relation) || !dgv2_is_current_period($relation)) {
            continue;
        }
        if (trim((string) ($relation['disciple_person_id'] ?? '')) !== $disciplePersonId) {
            continue;
        }
        $relation['end_date'] = today_date();
        $relation['status'] = 'closed';
        $relation['reason_end'] = $reason;
        $relation['updated_at'] = now_iso();
    }
    unset($relation);
}
