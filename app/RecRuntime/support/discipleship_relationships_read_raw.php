<?php

function discipleship_relationships_read_raw(): array {
    $database = discipleship_table_read_raw(DISCIPLESHIP_RELATIONSHIPS_DATA_NAME);
    $database['schema_version'] = (int) ($database['schema_version'] ?? 1);
    $database['name'] = DISCIPLESHIP_RELATIONSHIPS_DATA_NAME;
    $branches = is_array($database['branches'] ?? null) ? $database['branches'] : [];
    $database['branches'] = array_values(array_unique(array_filter(array_map(static function ($value) {
        $branch = strtolower(trim((string) $value));
        return is_known_public_branch_code($branch) ? normalize_public_branch_code($branch) : '';
    }, $branches))));
    sort($database['branches'], SORT_STRING);
    $database['records'] = is_array($database['records'] ?? null) ? array_values(array_filter($database['records'], 'is_array')) : [];
    return $database;
}
