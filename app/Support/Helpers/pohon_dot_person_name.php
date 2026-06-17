<?php

function pohon_dot_person_name(array $peopleById, string $personId): string {
    $name = trim((string) ($peopleById[$personId]['name'] ?? ''));
    return $name !== '' ? $name : $personId;
}
