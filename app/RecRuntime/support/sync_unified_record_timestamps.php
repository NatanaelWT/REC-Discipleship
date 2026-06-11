<?php

function sync_unified_record_timestamps(array $record): array {
    $memberPayload = is_array($record['member'] ?? null) ? $record['member'] : null;
    $mskPayload = is_array($record['msk'] ?? null) ? $record['msk'] : null;
    $discipleshipPayload = is_array($record['discipleship'] ?? null) ? $record['discipleship'] : null;
    $discipleshipPersonPayload = is_array($record['discipleship_person'] ?? null) ? $record['discipleship_person'] : null;
    $relationCreatedValues = [];
    $relationUpdatedValues = [];
    if ($discipleshipPersonPayload !== null) {
        foreach (discipleship_normalize_embedded_relation_container($discipleshipPersonPayload['relations'] ?? []) as $rows) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $relationCreatedValues[] = (string) ($row['created_at'] ?? '');
                $relationUpdatedValues[] = (string) ($row['updated_at'] ?? '');
            }
        }
    }

    $createdAt = normalize_iso_datetime_to_jakarta((string) ($record['created_at'] ?? ''));
    if ($createdAt === '') {
        $createdAt = first_iso_datetime(array_merge([
            (string) ($memberPayload['created_at'] ?? ''),
            (string) ($mskPayload['created_at'] ?? ''),
            (string) ($discipleshipPayload['created_at'] ?? ''),
            (string) ($discipleshipPersonPayload['created_at'] ?? ''),
        ], $relationCreatedValues));
    }
    if ($createdAt === '') {
        $createdAt = now_iso();
    }

    $updatedAt = normalize_iso_datetime_to_jakarta((string) ($record['updated_at'] ?? ''));
    $latestNestedUpdatedAt = latest_iso_datetime(array_merge([
        (string) ($memberPayload['updated_at'] ?? ''),
        (string) ($mskPayload['updated_at'] ?? ''),
        (string) ($discipleshipPayload['updated_at'] ?? ''),
        (string) ($discipleshipPersonPayload['updated_at'] ?? ''),
        $createdAt,
    ], $relationUpdatedValues));
    if ($updatedAt === '') {
        $updatedAt = $latestNestedUpdatedAt;
    } else {
        $updatedAt = latest_iso_datetime([$updatedAt, $latestNestedUpdatedAt]);
    }
    if ($updatedAt === '') {
        $updatedAt = $createdAt;
    }

    $record['created_at'] = $createdAt;
    $record['updated_at'] = $updatedAt;
    return $record;
}
