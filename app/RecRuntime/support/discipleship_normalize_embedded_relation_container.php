<?php

function discipleship_normalize_embedded_relation_container($value): array {
    if (!is_array($value)) {
        return [];
    }
    $relations = [];
    foreach (array_keys(discipleship_embedded_relation_table_names()) as $name) {
        $rows = $value[$name] ?? [];
        if (!is_array($rows)) {
            continue;
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            unset($row['cabang']);
            $relations[$name][] = $row;
        }
    }
    return $relations;
}
