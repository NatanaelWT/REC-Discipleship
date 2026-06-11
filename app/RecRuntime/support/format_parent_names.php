<?php

function format_parent_names(array $peopleById, array $parentIds): string {
    $names = [];
    foreach ($parentIds as $parentId) {
        $label = person_label($peopleById, (string) $parentId, '');
        if ($label !== '') {
            $names[] = $label;
        }
    }
    if (count($names) === 0) {
        return '-';
    }
    return implode(' & ', $names);
}
