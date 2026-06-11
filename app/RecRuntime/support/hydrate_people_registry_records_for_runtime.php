<?php

function hydrate_people_registry_records_for_runtime($records): array {
    if (!is_array($records)) {
        return [];
    }
    $hydrated = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $hydrated[] = hydrate_people_registry_record_for_runtime($record);
    }
    return $hydrated;
}
