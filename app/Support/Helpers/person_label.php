<?php

function person_label(array $peopleById, string $id, string $fallback = '-'): string {
    if ($id === '' || !isset($peopleById[$id])) {
        return $fallback;
    }
    $name = $peopleById[$id]['name'] ?? '';
    return $name !== '' ? $name : $fallback;
}
