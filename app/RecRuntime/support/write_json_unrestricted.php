<?php

function write_json_unrestricted(string $path, $data): bool {
    $info = discipleship_branch_path_info($path);
    if ($info !== null) {
        return discipleship_table_set_branch_value($info['branch'], $info['name'], $data);
    }

    if (\App\Support\LegacyDataStore::writePath($path, $data)) {
        return true;
    }

    $existingJson = is_file($path) ? file_get_contents($path) : false;
    $json = encode_json_for_storage($data, detect_preferred_json_eol($existingJson));
    if ($json === null) {
        return false;
    }
    if (is_file($path)) {
        if ($existingJson !== false && $existingJson === $json) {
            return true;
        }
    }
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
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
