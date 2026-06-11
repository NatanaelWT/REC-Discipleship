<?php

function compact_people_registry_records_for_storage(array $records): array {
    $compactRecords = [];
    foreach (normalize_people_registry_records($records) as $record) {
        if (!is_array($record)) {
            continue;
        }
        $record = sync_unified_record_timestamps($record);
        $recordId = trim((string) ($record['id'] ?? ''));
        if ($recordId === '') {
            continue;
        }

        $profile = is_array($record['profile'] ?? null) ? $record['profile'] : [];
        $memberPayload = is_array($record['member'] ?? null) ? $record['member'] : null;
        $mskPayload = is_array($record['msk'] ?? null) ? $record['msk'] : null;
        $discipleshipPayload = is_array($record['discipleship'] ?? null) ? $record['discipleship'] : null;
        $discipleshipPersonPayload = is_array($record['discipleship_person'] ?? null) ? $record['discipleship_person'] : null;

        unified_compact_profile_owned_payloads($profile, $discipleshipPayload, $discipleshipPersonPayload);

        if (is_array($memberPayload)) {
            unset($memberPayload['is_member']);
            if (trim((string) ($memberPayload['social_media'] ?? '')) === '') {
                unset($memberPayload['social_media']);
            }
            if (trim((string) ($memberPayload['left_reason'] ?? '')) === '') {
                unset($memberPayload['left_reason']);
            }
            if (trim((string) ($memberPayload['left_at'] ?? '')) === '') {
                unset($memberPayload['left_at']);
            }
            if (isset($memberPayload['family_ids']) && is_array($memberPayload['family_ids']) && count($memberPayload['family_ids']) === 0) {
                unset($memberPayload['family_ids']);
            }
        }
        if (is_array($mskPayload)) {
            unset($mskPayload['is_participant']);
            unset($mskPayload['source_type'], $mskPayload['converted_member_id']);
            if (trim((string) ($mskPayload['notes'] ?? '')) === '') {
                unset($mskPayload['notes']);
            }
            if (trim((string) ($mskPayload['completed_at'] ?? '')) === '') {
                unset($mskPayload['completed_at']);
            }
            if (normalize_journey_bridge_status((string) ($mskPayload['journey_bridge_status'] ?? 'belum')) === 'belum') {
                unset($mskPayload['journey_bridge_status']);
            }
            if (trim((string) ($mskPayload['member_id'] ?? '')) === '') {
                unset($mskPayload['member_id']);
            }
            if (normalize_msk_participant_status((string) ($mskPayload['status'] ?? 'active')) === 'active') {
                unset($mskPayload['status']);
            }
        }
        $cleanProfile = [];
        foreach ($profile as $profileKey => $profileValue) {
            if (is_string($profileValue) && trim($profileValue) === '') {
                continue;
            }
            if (is_array($profileValue) && count($profileValue) === 0) {
                continue;
            }
            $cleanProfile[$profileKey] = $profileValue;
        }
        $profile = $cleanProfile;

        $compactRow = [
            'id' => $recordId,
            'profile' => $profile,
            'created_at' => (string) ($record['created_at'] ?? now_iso()),
            'updated_at' => (string) ($record['updated_at'] ?? ($record['created_at'] ?? now_iso())),
        ];
        $recordBranch = trim((string) ($record['cabang'] ?? ''));
        if ($recordBranch !== '') {
            $compactRow['cabang'] = $recordBranch;
        }
        if (is_array($memberPayload)) {
            $compactRow['member'] = $memberPayload;
        }
        if (is_array($mskPayload)) {
            $compactRow['msk'] = $mskPayload;
        }
        if (is_array($discipleshipPayload)) {
            unset($discipleshipPayload['is_person']);
            $compactRow['discipleship'] = $discipleshipPayload;
        }
        if (is_array($discipleshipPersonPayload)) {
            unset($discipleshipPersonPayload['is_person']);
            $compactRow['discipleship_person'] = $discipleshipPersonPayload;
        }
        $compactRecord = flatten_people_registry_record_for_storage($compactRow);
        if ($compactRecord !== []) {
            $compactRecords[] = $compactRecord;
        }
    }
    return array_values($compactRecords);
}
