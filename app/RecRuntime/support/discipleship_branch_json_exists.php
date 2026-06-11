<?php

function discipleship_branch_json_exists(string $path): bool {
    $info = discipleship_branch_path_info($path);
    if ($info !== null && discipleship_table_has_logical_source($info['name'])) {
        $found = false;
        discipleship_table_branch_value($info['branch'], $info['name'], $found);
        if ($found) {
            return true;
        }
    }
    return is_file($path);
}
