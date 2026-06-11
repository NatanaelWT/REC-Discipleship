<?php

function discipleship_table_default(string $name): array {
    $name = canonical_data_name($name);
    return [
        'schema_version' => 1,
        'name' => $name,
        'updated_at' => '',
        'branches' => [],
        'records' => [],
    ];
}
