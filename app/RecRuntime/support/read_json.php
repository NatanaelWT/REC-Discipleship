<?php

function read_json(string $path, $default) {
    $info = discipleship_branch_path_info($path);
    if ($info !== null && discipleship_table_has_logical_source($info['name'])) {
        $found = false;
        $data = discipleship_table_branch_value($info['branch'], $info['name'], $found);
        if ($found && is_array($data)) {
            return $data;
        }
    }

    $found = false;
    $data = \App\Support\LegacyDataStore::readPath($path, $default, $found);
    if ($found && is_array($data)) {
        return $data;
    }

    if (!file_exists($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $default;
    }
    return $data;
}
