<?php

function dgv2_open_relation(array &$model, string $mentorPersonId, string $disciplePersonId, string $groupId = '', string $stage = ''): void {
    if ($mentorPersonId === '' || $disciplePersonId === '') {
        return;
    }
    $model['discipleship_relations'][] = [
        'id' => generate_id('dsr'),
        'mentor_person_id' => $mentorPersonId,
        'disciple_person_id' => $disciplePersonId,
        'context_group_id' => $groupId,
        'stage_at_start' => $stage,
        'relation_type' => 'memuridkan_langsung',
        'start_date' => today_date(),
        'end_date' => '',
        'status' => 'active',
        'created_at' => now_iso(),
        'updated_at' => now_iso(),
    ];
}
