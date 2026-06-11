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

    $json = json_encode($table, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return false;
    }

    $path = discipleship_table_path($name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    $existingJson = is_file($path) ? file_get_contents($path) : false;
    if ($existingJson !== false && $existingJson === $json) {
        return true;
    }

    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    $bytes = fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $bytes !== false;
}
