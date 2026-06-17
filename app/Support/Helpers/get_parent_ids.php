<?php

function get_parent_ids(array $person): array {
    $ids = $person['parent_ids'] ?? [];
    if (!is_array($ids)) {
        return [];
    }
    $clean = [];
    foreach ($ids as $id) {
        $id = (string) $id;
        if ($id === '') {
            continue;
        }
        $clean[] = $id;
    }
    return array_values(array_unique($clean));
}
