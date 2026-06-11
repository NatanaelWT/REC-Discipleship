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
    $path = discipleship_table_path($name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }

    flock($fp, LOCK_EX);
    rewind($fp);
    $raw = stream_get_contents($fp);
    $database = null;
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $database = $decoded;
        }
    }
    if (!is_array($database)) {
        $database = discipleship_table_default($name);
    }
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

    $json = json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
    if (is_string($raw) && $raw === $json) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    rewind($fp);
    ftruncate($fp, 0);
    $bytes = fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $bytes !== false;
}
