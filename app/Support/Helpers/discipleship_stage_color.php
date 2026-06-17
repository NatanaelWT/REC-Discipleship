<?php

function discipleship_stage_color(string $stage): string {
    $normalizedStage = strtolower(trim($stage));
    if ($normalizedStage === 'msk') {
        return '#0284c7';
    }
    if ($normalizedStage === 'kgap' || $normalizedStage === 'kamp gap' || $normalizedStage === 'target kamp gap') {
        return '#0f766e';
    }
    $normalizedProgress = normalize_dg_progress_value($stage);
    if ($normalizedProgress === 'DG 1') {
        return '#d97706';
    }
    if ($normalizedProgress === 'DG 2') {
        return '#7c3aed';
    }
    if ($normalizedProgress === 'DG 3') {
        return '#dc2626';
    }
    return '#94a3b8';
}
