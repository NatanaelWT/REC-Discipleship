<?php

function write_json(string $path, $data): void {
    if (is_effective_central_discipleship_readonly()) {
        return;
    }
    $info = discipleship_branch_path_info($path);
    if ($info !== null) {
        discipleship_table_set_branch_value($info['branch'], $info['name'], $data);
        return;
    }

    if (\App\Support\LegacyDataStore::writePath($path, $data)) {
        return;
    }

    $existingJson = is_file($path) ? file_get_contents($path) : false;
    $json = encode_json_for_storage($data, detect_preferred_json_eol($existingJson));
    if ($json === null) {
        return;
    }
    if (is_file($path)) {
        if ($existingJson !== false && $existingJson === $json) {
            return;
        }
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return;
    }
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}
