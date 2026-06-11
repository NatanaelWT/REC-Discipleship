<?php

function read_public_member_feedback_rows(string $branch): array {
    $rows = read_json(scoped_data_path('dg_member_feedback_journals', normalize_public_branch_code($branch)), []);
    if (!is_array($rows)) {
        return [];
    }
    return array_values(array_filter($rows, function ($row) {
        return is_array($row);
    }));
}
