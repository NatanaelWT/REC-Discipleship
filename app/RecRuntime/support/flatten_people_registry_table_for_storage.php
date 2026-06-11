<?php

function flatten_people_registry_table_for_storage(array $table): array {
    $records = $table['records'] ?? [];
    $table['records'] = compact_people_registry_records_for_storage(is_array($records) ? $records : []);

    $branches = is_array($table['branches'] ?? null) ? $table['branches'] : [];
    foreach ($table['records'] as $record) {
        $branch = discipleship_table_branch_from_record($record);
        if ($branch !== '') {
            $branches[] = $branch;
        }
    }
    $branches = array_values(array_unique(array_filter(array_map(static function ($branch) {
        $branch = strtolower(trim((string) $branch));
        return is_known_public_branch_code($branch) ? normalize_public_branch_code($branch) : '';
    }, $branches))));
    sort($branches, SORT_STRING);
    $table['branches'] = $branches;
    $table['name'] = PEOPLE_REGISTRY_DATA_NAME;
    if (!isset($table['schema_version'])) {
        $table['schema_version'] = 1;
    }
    return $table;
}
