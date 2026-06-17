<?php

function canonical_data_name(string $name): string {
    $name = trim($name);
    return [
        'groups_v2' => DISCIPLESHIP_GROUPS_DATA_NAME,
    ][$name] ?? $name;
}
