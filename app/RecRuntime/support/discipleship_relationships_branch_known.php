<?php

function discipleship_relationships_branch_known(string $branch): bool {
    if (!discipleship_relationship_database_exists()) {
        return false;
    }
    $branch = normalize_public_branch_code($branch);
    $database = discipleship_relationships_read_raw();
    $branches = is_array($database['branches'] ?? null) ? $database['branches'] : [];
    if (in_array($branch, array_map('strval', $branches), true)) {
        return true;
    }
    foreach (($database['records'] ?? []) as $record) {
        if (discipleship_table_branch_from_record($record) === $branch) {
            return true;
        }
    }
    return false;
}
