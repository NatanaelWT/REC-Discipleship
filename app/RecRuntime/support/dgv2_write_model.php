<?php

function dgv2_write_model(string $branch, array $model): void {
    foreach (dgv2_empty_model() as $name => $_unused) {
        $rows = $model[$name] ?? [];
        write_json(dgv2_branch_file_path($name, $branch), is_array($rows) ? array_values($rows) : []);
    }
}
