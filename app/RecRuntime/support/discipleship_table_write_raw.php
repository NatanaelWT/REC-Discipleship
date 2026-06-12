<?php

function discipleship_table_write_raw(string $name, array $table): bool {
    $name = canonical_data_name($name);
    if (!isset($table['schema_version'])) {
        $table['schema_version'] = 1;
    }
    $table['name'] = $name;
    $table['updated_at'] = discipleship_table_now_iso();
    if (!isset($table['branches']) || !is_array($table['branches'])) {
        $table['branches'] = [];
    }
    if (!isset($table['records']) || !is_array($table['records'])) {
        $table['records'] = [];
    }
    if ($name === PEOPLE_REGISTRY_DATA_NAME) {
        $table = flatten_people_registry_table_for_storage($table);
    }

    return \App\Support\LegacyDataStore::writeDocumentTable($name, $table);
}
