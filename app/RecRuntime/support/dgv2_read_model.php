<?php

function dgv2_read_model(string $branch): array {
    $model = dgv2_empty_model();
    foreach (array_keys($model) as $name) {
        $rows = read_json(dgv2_branch_file_path($name, $branch), []);
        $model[$name] = is_array($rows) ? array_values($rows) : [];
    }
    return $model;
}
