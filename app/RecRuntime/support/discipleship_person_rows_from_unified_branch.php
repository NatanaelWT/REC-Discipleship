<?php

function discipleship_person_rows_from_unified_branch(string $branch): array {
    if (!discipleship_persons_are_unified()) {
        return [];
    }
    $branch = normalize_public_branch_code($branch);
    $table = discipleship_table_read_raw(PEOPLE_REGISTRY_DATA_NAME);
    $records = $table['records'] ?? [];
    if (!is_array($records)) {
        return [];
    }
    $rows = [];
    foreach ($records as $record) {
        if (!is_array($record) || discipleship_table_branch_from_record($record) !== $branch) {
            continue;
        }
        $row = discipleship_person_row_from_unified_record($record);
        if ($row !== null) {
            $rows[] = $row;
        }
    }
    return array_values($rows);
}
