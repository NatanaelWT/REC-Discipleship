<?php

function discipleship_embedded_relation_owner_field(string $name): string {
    return [
        'discipleship_relations' => 'disciple_person_id',
        'group_memberships' => 'person_id',
        'group_leaderships' => 'leader_person_id',
        'group_multiplications' => 'initiated_by_person_id',
    ][$name] ?? '';
}
