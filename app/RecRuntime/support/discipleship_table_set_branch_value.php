<?php

function discipleship_table_set_branch_value(string $branch, string $name, $data): bool {
    $name = canonical_data_name($name);
    if (!isset(branch_scoped_data_names()[$name])) {
        return false;
    }
    if ($name === 'discipleship_persons') {
        return discipleship_persons_set_unified_branch_value($branch, $data);
    }
    if (isset(discipleship_embedded_relation_table_names()[$name])) {
        return discipleship_relationship_set_branch_value($branch, $name, $data);
    }

    $branch = normalize_public_branch_code($branch);
    $database = discipleship_table_read_raw($name);
    if (!isset($database['schema_version'])) {
        $database['schema_version'] = 1;
    }
    $database['name'] = $name;
    if (!isset($database['branches']) || !is_array($database['branches'])) {
        $database['branches'] = [];
    }
    if (!in_array($branch, $database['branches'], true)) {
        $database['branches'][] = $branch;
        sort($database['branches'], SORT_STRING);
    }
    if (!isset($database['records']) || !is_array($database['records'])) {
        $database['records'] = [];
    }

    $records = [];
    foreach ($database['records'] as $record) {
        if (discipleship_table_branch_from_record($record) === $branch) {
            continue;
        }
        if (is_array($record)) {
            $records[] = $record;
        }
    }

    if (isset(discipleship_table_object_data_names()[$name])) {
        $records[] = discipleship_table_encode_branch_record($branch, $data);
    } else {
        if (!is_array($data)) {
            $data = [];
        }
        foreach ($data as $row) {
            if (is_array($row)) {
                $records[] = discipleship_table_encode_branch_record($branch, $row);
            }
        }
    }

    $database['records'] = $records;
    $database['updated_at'] = discipleship_table_now_iso();
    if ($name === PEOPLE_REGISTRY_DATA_NAME) {
        $database = flatten_people_registry_table_for_storage($database);
    }

    return discipleship_table_write_raw($name, $database);
}
