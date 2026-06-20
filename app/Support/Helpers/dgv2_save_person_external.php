<?php

function dgv2_save_person_external(array &$model, array $payload): array
{
    $leaderId = trim((string) ($payload['leader_id'] ?? ''));
    $groupId = trim((string) ($payload['group_id'] ?? ''));
    $fullName = trim((string) ($payload['full_name'] ?? ''));
    $notes = trim((string) ($payload['notes'] ?? ''));

    if ($leaderId !== 'virtual_injil' || $groupId !== '') {
        return ['ok' => false, 'error' => 'missing_member'];
    }
    if ($fullName === '') {
        return ['ok' => false, 'error' => 'missing_person_name'];
    }

    foreach ($model['discipleship_persons'] as $row) {
        if (! is_array($row)) {
            continue;
        }
        $rowStatus = strtolower(trim((string) ($row['status'] ?? 'active')));
        $rowName = strtolower(trim((string) ($row['full_name'] ?? '')));
        $rowMemberId = trim((string) ($row['member_id'] ?? ''));
        if ($rowStatus === 'active' && $rowMemberId === '' && $rowName !== '' && $rowName === strtolower($fullName)) {
            return ['ok' => false, 'error' => 'member_exists'];
        }
    }

    $personId = temporary_model_id('person');
    $now = now_iso();
    $model['discipleship_persons'][] = [
        'id' => $personId,
        'member_id' => '',
        'full_name' => $fullName,
        'phone' => '',
        'gender' => '',
        'status' => 'active',
        'notes' => $notes,
        'kampus' => '',
        'jurusan' => '',
        'pekerjaan' => '',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    dgv2_close_active_relation_for_disciple($model, $personId);
    dgv2_open_relation($model, $leaderId, $personId, '', '');

    return ['ok' => true, 'person_id' => $personId];
}
