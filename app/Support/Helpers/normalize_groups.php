<?php

function normalize_groups(array &$groups, array $peopleById): bool {
    $progressOptions = ['DG 1', 'DG 2', 'DG 3'];
    $changed = false;
    $cleanGroups = [];
    foreach ($groups as $grp) {
        if (!is_array($grp)) {
            $changed = true;
            continue;
        }
        $leaderId = trim((string) ($grp['leader_id'] ?? ''));
        if ($leaderId === '' || !isset($peopleById[$leaderId])) {
            $changed = true;
            continue;
        }
        $existingMemberNameMap = normalize_group_member_names($grp['member_names'] ?? []);
        $leaderName = trim((string) ($peopleById[$leaderId]['name'] ?? ''));
        if ($leaderName === '' && $leaderId !== '') {
            $leaderName = 'Leader #' . $leaderId;
        }

        $memberIds = $grp['member_ids'] ?? [];
        if (!is_array($memberIds)) {
            $memberIds = [];
        }
        $filtered = [];
        foreach ($memberIds as $mid) {
            $mid = (string) $mid;
            if ($mid === '') {
                continue;
            }
            if (!isset($peopleById[$mid])) {
                continue;
            }
            $leaderIds = get_parent_ids($peopleById[$mid]);
            $primaryLeader = $leaderIds[0] ?? '';
            if ($primaryLeader !== $leaderId) {
                continue;
            }
            $filtered[] = $mid;
        }
        $filtered = array_values(array_unique($filtered));
        $memberNameMap = build_group_member_names($filtered, $peopleById, $existingMemberNameMap);

        $progress = '';
        $progressRaw = $grp['progress'] ?? '';
        if (is_string($progressRaw)) {
            $candidate = trim($progressRaw);
            if (in_array($candidate, $progressOptions, true)) {
                $progress = $candidate;
            }
        } elseif (is_numeric($progressRaw)) {
            $num = (int) $progressRaw;
            if ($num >= 1 && $num <= 3) {
                $progress = 'DG ' . $num;
            }
        }
        $assistantId = trim((string) ($grp['assistant_id'] ?? ''));
        if ($assistantId === $leaderId) {
            $assistantId = '';
        }
        if ($assistantId !== '' && !isset($peopleById[$assistantId])) {
            $assistantId = '';
        }
        $clean = [
            'id' => $grp['id'] ?? generate_id('grp'),
            'leader_id' => $leaderId,
            'assistant_id' => $assistantId,
            'member_ids' => $filtered,
            'leader_name' => $leaderName,
            'member_names' => $memberNameMap,
            'progress' => $progress,
            'notes' => trim((string) ($grp['notes'] ?? '')),
            'updated_at' => $grp['updated_at'] ?? now_iso(),
            'created_at' => $grp['created_at'] ?? now_iso(),
        ];
        if (
            ($grp['id'] ?? '') === ''
            || trim((string) ($grp['leader_id'] ?? '')) !== $leaderId
            || trim((string) ($grp['assistant_id'] ?? '')) !== $assistantId
            || ($grp['member_ids'] ?? null) !== $filtered
            || (string) ($grp['progress'] ?? '') !== $progress
            || array_key_exists('name', $grp)
            || trim((string) ($grp['notes'] ?? '')) !== trim((string) ($clean['notes'] ?? ''))
        ) {
            $changed = true;
        }
        $cleanGroups[] = $clean;
    }
    if (count($cleanGroups) !== count($groups)) {
        $changed = true;
    }
    $groups = array_values($cleanGroups);
    return $changed;
}
