<?php

function discipleship_persons_set_unified_branch_value(string $branch, $data): bool {
    $branch = normalize_public_branch_code($branch);
    $table = discipleship_table_read_raw(PEOPLE_REGISTRY_DATA_NAME);
    $records = $table['records'] ?? [];
    if (!is_array($records)) {
        $records = [];
    }

    $nextRecords = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        if (discipleship_table_branch_from_record($record) === $branch) {
            unset($record['discipleship_person']);
            if (!discipleship_unified_record_has_payload($record)) {
                continue;
            }
        }
        $nextRecords[] = $record;
    }

    $indexByAlias = [];
    foreach ($nextRecords as $idx => $record) {
        if (!is_array($record) || discipleship_table_branch_from_record($record) !== $branch) {
            continue;
        }
        foreach (discipleship_unified_record_aliases($record) as $alias) {
            if (!isset($indexByAlias[$alias])) {
                $indexByAlias[$alias] = $idx;
            }
        }
    }

    if (!is_array($data)) {
        $data = [];
    }
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $payload = unified_discipleship_person_payload($row);
        $personId = trim((string) ($payload['person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $memberId = trim((string) ($payload['member_id'] ?? ''));
        $targetIdx = null;
        if (isset($indexByAlias[$personId])) {
            $candidateIdx = $indexByAlias[$personId];
            $existingPersonId = trim((string) ($nextRecords[$candidateIdx]['discipleship_person']['person_id'] ?? ''));
            if ($existingPersonId === '' || $existingPersonId === $personId) {
                $targetIdx = $candidateIdx;
            }
        }
        if ($targetIdx === null && $memberId !== '' && isset($indexByAlias[$memberId])) {
            $candidateIdx = $indexByAlias[$memberId];
            $existingPersonId = trim((string) ($nextRecords[$candidateIdx]['discipleship_person']['person_id'] ?? ''));
            if ($existingPersonId === '' || $existingPersonId === $personId) {
                $targetIdx = $candidateIdx;
            }
        }
        if ($targetIdx === null) {
            $targetIdx = count($nextRecords);
            $recordId = $memberId !== '' && !isset($indexByAlias[$memberId]) ? $memberId : $personId;
            $nextRecords[] = [
                'cabang' => $branch,
                'id' => $recordId,
                'profile' => [],
                'created_at' => (string) ($payload['created_at'] ?? discipleship_table_now_iso()),
                'updated_at' => (string) ($payload['updated_at'] ?? discipleship_table_now_iso()),
            ];
        }

        if (!is_array($nextRecords[$targetIdx]['profile'] ?? null)) {
            $nextRecords[$targetIdx]['profile'] = [];
        }
        if (trim((string) ($payload['full_name'] ?? '')) !== '') {
            $nextRecords[$targetIdx]['profile']['full_name'] = trim((string) ($payload['full_name'] ?? ''));
        }
        if (trim((string) ($payload['phone'] ?? '')) !== '') {
            $nextRecords[$targetIdx]['profile']['whatsapp'] = trim((string) ($payload['phone'] ?? ''));
        }
        if (trim((string) ($payload['gender'] ?? '')) !== '') {
            $nextRecords[$targetIdx]['profile']['gender'] = normalize_member_gender_value((string) ($payload['gender'] ?? ''));
        }
        $profileForStorage = is_array($nextRecords[$targetIdx]['profile'] ?? null) ? $nextRecords[$targetIdx]['profile'] : [];
        $nullDiscipleshipPayload = null;
        unified_compact_profile_owned_payloads($profileForStorage, $nullDiscipleshipPayload, $payload);
        $nextRecords[$targetIdx]['profile'] = $profileForStorage;
        $nextRecords[$targetIdx]['discipleship_person'] = $payload;
        $nextRecords[$targetIdx] = sync_unified_record_timestamps($nextRecords[$targetIdx]);
        $indexByAlias[$personId] = $targetIdx;
        if ($memberId !== '' && !isset($indexByAlias[$memberId])) {
            $indexByAlias[$memberId] = $targetIdx;
        }
    }

    $branches = [];
    foreach ($nextRecords as $record) {
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
    $table['records'] = array_values($nextRecords);
    $table['updated_at'] = discipleship_table_now_iso();
    $table = flatten_people_registry_table_for_storage($table);

    return discipleship_table_write_raw(PEOPLE_REGISTRY_DATA_NAME, $table);
}
