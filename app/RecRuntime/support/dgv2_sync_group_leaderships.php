<?php

function dgv2_sync_group_leaderships(array &$model, string $groupId, string $leaderId, string $assistantId): void {
    $hasActiveLeader = false;
    $hasActiveAssistant = false;
    foreach ($model['group_leaderships'] as &$leadership) {
        if (!is_array($leadership) || !dgv2_is_current_period($leadership)) {
            continue;
        }
        if (trim((string) ($leadership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $currentPersonId = trim((string) ($leadership['leader_person_id'] ?? ''));
        $role = strtolower(trim((string) ($leadership['role'] ?? 'leader')));
        $targetPersonId = ($role === 'co_leader' || $role === 'assistant') ? $assistantId : $leaderId;
        if ($currentPersonId === $targetPersonId && $targetPersonId !== '') {
            if ($role === 'co_leader' || $role === 'assistant') {
                $hasActiveAssistant = true;
            } else {
                $hasActiveLeader = true;
            }
            continue;
        }
        $leadership['end_date'] = today_date();
        $leadership['status'] = 'closed';
        $leadership['reason_change'] = 'changed_leadership';
        $leadership['updated_at'] = now_iso();
    }
    unset($leadership);

    if ($leaderId !== '' && !$hasActiveLeader) {
        $model['group_leaderships'][] = [
            'id' => generate_id('gld'),
            'group_id' => $groupId,
            'leader_person_id' => $leaderId,
            'role' => 'leader',
            'start_date' => today_date(),
            'end_date' => '',
            'status' => 'active',
            'reason_change' => '',
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];
    }
    if ($assistantId !== '' && !$hasActiveAssistant) {
        $model['group_leaderships'][] = [
            'id' => generate_id('gld'),
            'group_id' => $groupId,
            'leader_person_id' => $assistantId,
            'role' => 'co_leader',
            'start_date' => today_date(),
            'end_date' => '',
            'status' => 'active',
            'reason_change' => '',
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];
    }
}
