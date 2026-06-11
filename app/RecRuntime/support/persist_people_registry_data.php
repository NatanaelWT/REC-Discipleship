<?php

function persist_people_registry_data(array $members, array $mskClasses, ?array $people = null): void {
    global $memberMskUnifiedRecords;
    if (!isset($memberMskUnifiedRecords) || !is_array($memberMskUnifiedRecords)) {
        $memberMskUnifiedRecords = [];
    }
    $peopleData = $people ?? [];
    $memberMskUnifiedRecords = merge_people_registry_records($memberMskUnifiedRecords, $members, $mskClasses, $peopleData);
    $compactRecords = compact_people_registry_records_for_storage($memberMskUnifiedRecords);
    $memberMskUnifiedRecords = $compactRecords;
    write_json(data_path(PEOPLE_REGISTRY_DATA_NAME), $compactRecords);
}
