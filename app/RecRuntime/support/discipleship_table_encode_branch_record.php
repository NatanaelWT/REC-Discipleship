<?php

function discipleship_table_encode_branch_record(string $branch, $record): array {
    if (!is_array($record)) {
        $record = [];
    }
    unset($record['cabang']);
    return array_merge(['cabang' => normalize_public_branch_code($branch)], $record);
}
