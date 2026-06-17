<?php

function read_branch_discipleship_targets(string $branch): array {
    $branch = normalize_user_branch($branch);
    if (class_exists(\App\Services\DiscipleshipTargets\DiscipleshipTargetReader::class) && function_exists('app')) {
        return app(\App\Services\DiscipleshipTargets\DiscipleshipTargetReader::class)->formValuesForBranch($branch);
    }

    return normalize_discipleship_targets(default_discipleship_targets());
}
