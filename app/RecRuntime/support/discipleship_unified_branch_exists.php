<?php

function discipleship_unified_branch_exists(string $branch): bool {
    if (!discipleship_persons_are_unified()) {
        return false;
    }
    $branch = normalize_public_branch_code($branch);
    $table = discipleship_table_read_raw(PEOPLE_REGISTRY_DATA_NAME);
    $records = $table['records'] ?? [];
    if (!is_array($records)) {
        return false;
    }
    foreach ($records as $record) {
        if (discipleship_table_branch_from_record($record) === $branch) {
            return true;
        }
    }
    return false;
}
