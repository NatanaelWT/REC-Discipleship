<?php

function discipleship_relationship_rows_from_branch(string $branch, string $name): array {
    $kind = discipleship_relationship_kind_for_table($name);
    if ($kind === '' || !discipleship_relationship_database_exists()) {
        return [];
    }
    $branch = normalize_public_branch_code($branch);
    $database = discipleship_relationships_read_raw();
    $rows = [];
    foreach (($database['records'] ?? []) as $record) {
        if (!is_array($record)) {
            continue;
        }
        if (discipleship_table_branch_from_record($record) !== $branch) {
            continue;
        }
        if (trim((string) ($record['relationship_kind'] ?? '')) !== $kind) {
            continue;
        }
        unset($record['cabang'], $record['relationship_kind']);
        $rows[] = $record;
    }
    return array_values($rows);
}
