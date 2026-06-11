<?php

function discipleship_embedded_relation_rows_from_unified_branch(string $branch, string $name): array {
    if (!discipleship_persons_are_unified() || !isset(discipleship_embedded_relation_table_names()[$name])) {
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
        $payload = is_array($record['discipleship_person'] ?? null) ? $record['discipleship_person'] : [];
        $relations = discipleship_normalize_embedded_relation_container($payload['relations'] ?? []);
        foreach (($relations[$name] ?? []) as $row) {
            if (is_array($row)) {
                unset($row['cabang']);
                $rows[] = $row;
            }
        }
    }
    return array_values($rows);
}
