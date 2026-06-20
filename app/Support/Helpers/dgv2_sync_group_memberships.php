<?php

function dgv2_sync_group_memberships(array &$model, string $groupId, array $memberIds, string $stage): void
{
    $activeMembershipIds = [];
    foreach ($model['group_memberships'] as $index => $membership) {
        if (! is_array($membership) || ! dgv2_is_current_period($membership)) {
            continue;
        }
        if (trim((string) ($membership['group_id'] ?? '')) !== $groupId) {
            continue;
        }
        $personId = trim((string) ($membership['person_id'] ?? ''));
        if ($personId !== '') {
            $activeMembershipIds[$personId] = $index;
        }
    }

    foreach ($activeMembershipIds as $personId => $index) {
        if (! in_array($personId, $memberIds, true)) {
            $model['group_memberships'][$index]['end_date'] = today_date();
            $model['group_memberships'][$index]['status'] = 'closed';
            $model['group_memberships'][$index]['reason_end'] = 'removed_from_group';
            $model['group_memberships'][$index]['updated_at'] = now_iso();
            unset($activeMembershipIds[$personId]);

            continue;
        }
        $currentStage = normalize_dg_progress_value((string) ($model['group_memberships'][$index]['stage'] ?? ''));
        if ($currentStage !== $stage) {
            $model['group_memberships'][$index]['end_date'] = today_date();
            $model['group_memberships'][$index]['status'] = 'closed';
            $model['group_memberships'][$index]['reason_end'] = 'stage_transition';
            $model['group_memberships'][$index]['updated_at'] = now_iso();
            unset($activeMembershipIds[$personId]);
        }
    }

    foreach ($memberIds as $personId) {
        if ($personId === '' || isset($activeMembershipIds[$personId])) {
            continue;
        }
        $model['group_memberships'][] = [
            'id' => temporary_model_id('membership'),
            'person_id' => $personId,
            'group_id' => $groupId,
            'role' => 'member',
            'stage' => $stage,
            'start_date' => today_date(),
            'end_date' => '',
            'status' => 'active',
            'reason_end' => '',
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];
    }
}
