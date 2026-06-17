<?php

function public_member_feedback_group_title(array $groupRow): string {
    $progress = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
    if ($progress === '') {
        $progress = 'DG';
    }
    $leaderName = trim((string) ($groupRow['leader_name'] ?? ''));
    if ($leaderName !== '') {
        return $progress . ' (' . $leaderName . ')';
    }
    return $progress;
}
