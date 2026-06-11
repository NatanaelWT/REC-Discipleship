<?php

function discipleship_table_path(string $name): string {
    $name = canonical_data_name($name);
    return legacy_runtime_path('data/' . $name . '.json');
}
