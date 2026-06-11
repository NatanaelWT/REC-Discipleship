<?php

function discipleship_table_read_raw(string $name): array {
    $name = canonical_data_name($name);
    $path = discipleship_table_path($name);
    if (!is_file($path)) {
        return discipleship_table_default($name);
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return discipleship_table_default($name);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return discipleship_table_default($name);
    }

    if (!isset($data['schema_version'])) {
        $data['schema_version'] = 1;
    }
    if (!isset($data['name']) || !is_string($data['name'])) {
        $data['name'] = $name;
    }
    if (!isset($data['updated_at']) || !is_string($data['updated_at'])) {
        $data['updated_at'] = '';
    }
    if (!isset($data['branches']) || !is_array($data['branches'])) {
        $data['branches'] = [];
    }
    if (!isset($data['records']) || !is_array($data['records'])) {
        $data['records'] = [];
    }
    if ($name === PEOPLE_REGISTRY_DATA_NAME) {
        $data['records'] = hydrate_people_registry_records_for_runtime($data['records']);
    }
    return $data;
}
