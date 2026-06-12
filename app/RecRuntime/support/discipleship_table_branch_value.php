<?php

function discipleship_table_branch_value(string $branch, string $name, ?bool &$found = null) {
    $name = canonical_data_name($name);
    $found = false;
    if ($name === 'discipleship_persons' && discipleship_persons_are_unified()) {
        $rows = discipleship_person_rows_from_unified_branch($branch);
        if ($rows !== []) {
            $found = true;
            return $rows;
        }
        if (discipleship_unified_branch_exists($branch)) {
            $found = true;
            return [];
        }
        return null;
    }
    if (isset(discipleship_embedded_relation_table_names()[$name])) {
        $rows = discipleship_relationship_rows_from_branch($branch, $name);
        if ($rows !== []) {
            $found = true;
            return $rows;
        }
        if (discipleship_relationships_branch_known($branch)) {
            $found = true;
            return [];
        }
    }
    if (isset(discipleship_embedded_relation_table_names()[$name]) && discipleship_persons_are_unified()) {
        $rows = discipleship_embedded_relation_rows_from_unified_branch($branch, $name);
        if ($rows !== []) {
            $found = true;
            return $rows;
        }
        if (discipleship_unified_branch_exists($branch)) {
            $found = true;
            return [];
        }
        return null;
    }

    if (!discipleship_table_has_logical_source($name)) {
        return null;
    }

    $branch = normalize_public_branch_code($branch);
    $table = discipleship_table_read_raw($name);
    $branches = $table['branches'] ?? [];
    if (!is_array($branches)) {
        $branches = [];
    }
    $branchKnown = in_array($branch, $branches, true);
    $records = $table['records'] ?? [];
    if (!is_array($records)) {
        return null;
    }

    if (isset(discipleship_table_object_data_names()[$name])) {
        foreach ($records as $record) {
            if (discipleship_table_branch_from_record($record) !== $branch) {
                continue;
            }
            $found = true;
            return discipleship_table_strip_branch($record);
        }
        if ($branchKnown) {
            $found = true;
            return [];
        }
        return null;
    }

    $rows = [];
    foreach ($records as $record) {
        if (discipleship_table_branch_from_record($record) !== $branch) {
            continue;
        }
        $rows[] = discipleship_table_strip_branch($record);
    }
    if ($rows !== [] || $branchKnown) {
        $found = true;
        return $rows;
    }
    return null;
}
