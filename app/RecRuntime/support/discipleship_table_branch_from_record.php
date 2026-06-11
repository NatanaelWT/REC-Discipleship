<?php

function discipleship_table_branch_from_record($record): string {
    if (!is_array($record)) {
        return '';
    }
    $branch = strtolower(trim((string) ($record['cabang'] ?? '')));
    if (!is_known_public_branch_code($branch)) {
        return '';
    }
    return normalize_public_branch_code($branch);
}
