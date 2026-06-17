<?php

function dg_progress_min_share_times(string $progress): int {
    $progress = trim($progress);
    if (preg_match('/^DG\s*([1-3])$/i', $progress, $match) === 1) {
        return (int) $match[1];
    }
    if (is_numeric($progress)) {
        $num = (int) $progress;
        if ($num >= 1 && $num <= 3) {
            return $num;
        }
    }
    return 2;
}
