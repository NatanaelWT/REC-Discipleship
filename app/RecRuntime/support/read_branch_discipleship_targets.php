<?php

function read_branch_discipleship_targets(string $branch): array {
    $branch = normalize_user_branch($branch);
    $defaults = default_discipleship_targets();
    $path = scoped_data_path('discipleship_targets', $branch);
    return normalize_discipleship_targets(read_json($path, $defaults));
}
