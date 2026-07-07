<?php

function discipleship_group_display_label(array $groupRow, string $fallback = 'Kelompok DG'): string
{
    $stage = discipleship_group_stage_value($groupRow);
    $leaderName = trim((string) ($groupRow['leader_name'] ?? ''));
    if ($leaderName === '-') {
        $leaderName = '';
    }

    if ($stage !== '' && $leaderName !== '') {
        return $stage.' ('.$leaderName.')';
    }

    if ($stage !== '') {
        return $stage;
    }

    return $fallback;
}
