<?php

function discipleship_embedded_relation_set_unified_branch_value(string $branch, string $name, $data): bool {
    if (!isset(discipleship_embedded_relation_table_names()[$name])) {
        return false;
    }
    $ownerField = discipleship_embedded_relation_owner_field($name);
    if ($ownerField === '') {
        return false;
    }

    $branch = normalize_public_branch_code($branch);
    $path = discipleship_table_path(PEOPLE_REGISTRY_DATA_NAME);
    $table = discipleship_table_read_raw(PEOPLE_REGISTRY_DATA_NAME);
    $records = $table['records'] ?? [];
    if (!is_array($records)) {
        $records = [];
    }

    $indexByPersonId = [];
    foreach ($records as $idx => $record) {
        if (!is_array($record) || discipleship_table_branch_from_record($record) !== $branch) {
            continue;
        }
        if (!is_array($record['discipleship_person'] ?? null)) {
            continue;
        }
        $payload = $record['discipleship_person'];
        $relations = discipleship_normalize_embedded_relation_container($payload['relations'] ?? []);
        unset($relations[$name]);
        if ($relations === []) {
            unset($payload['relations']);
        } else {
            $payload['relations'] = $relations;
        }
        $record['discipleship_person'] = $payload;
        $records[$idx] = $record;

        $personId = trim((string) ($payload['person_id'] ?? $payload['id'] ?? ''));
        if ($personId !== '') {
            $indexByPersonId[$personId] = $idx;
        }
    }

    if (!is_array($data)) {
        $data = [];
    }
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $ownerPersonId = trim((string) ($row[$ownerField] ?? ''));
        if ($ownerPersonId === '') {
            continue;
        }
        if (!isset($indexByPersonId[$ownerPersonId])) {
            $indexByPersonId[$ownerPersonId] = count($records);
            $records[] = [
                'cabang' => $branch,
                'id' => $ownerPersonId,
                'profile' => [],
                'discipleship_person' => unified_discipleship_person_payload([
                    'id' => $ownerPersonId,
                    'status' => 'active',
                ]),
                'created_at' => discipleship_table_now_iso(),
                'updated_at' => discipleship_table_now_iso(),
            ];
        }

        $targetIdx = $indexByPersonId[$ownerPersonId];
        if (!is_array($records[$targetIdx]['discipleship_person'] ?? null)) {
            $records[$targetIdx]['discipleship_person'] = unified_discipleship_person_payload([
                'id' => $ownerPersonId,
                'status' => 'active',
            ]);
        }
        $payload = $records[$targetIdx]['discipleship_person'];
        $relations = discipleship_normalize_embedded_relation_container($payload['relations'] ?? []);
        unset($row['cabang']);
        $relations[$name][] = $row;
        $payload['relations'] = $relations;
        $records[$targetIdx]['discipleship_person'] = $payload;
        $records[$targetIdx] = sync_unified_record_timestamps($records[$targetIdx]);
    }

    $branches = [];
    foreach ($records as $record) {
        $recordBranch = discipleship_table_branch_from_record($record);
        if ($recordBranch !== '') {
            $branches[] = $recordBranch;
        }
    }
    $branches = array_values(array_unique($branches));
    sort($branches, SORT_STRING);
    $table['schema_version'] = 1;
    $table['name'] = PEOPLE_REGISTRY_DATA_NAME;
    $table['branches'] = $branches;
    $table['records'] = array_values(array_filter($records, 'is_array'));
    $table['updated_at'] = discipleship_table_now_iso();
    $table = flatten_people_registry_table_for_storage($table);

    $json = json_encode($table, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return false;
    }
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }
    return file_put_contents($path, $json) !== false;
}
