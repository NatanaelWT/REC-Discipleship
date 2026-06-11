<?php

function flatten_people_registry_record_for_storage(array $record): array {
    $record = hydrate_people_registry_record_for_runtime($record);
    $record = sync_unified_record_timestamps($record);
    $recordId = trim((string) ($record['id'] ?? ''));
    if ($recordId === '') {
        return [];
    }

    $profile = is_array($record['profile'] ?? null) ? $record['profile'] : [];
    $memberPayload = is_array($record['member'] ?? null) ? $record['member'] : null;
    $mskPayload = is_array($record['msk'] ?? null) ? $record['msk'] : null;
    $discipleshipPayload = is_array($record['discipleship'] ?? null) ? $record['discipleship'] : null;
    $discipleshipPersonPayload = is_array($record['discipleship_person'] ?? null) ? $record['discipleship_person'] : null;
    unified_compact_profile_owned_payloads($profile, $discipleshipPayload, $discipleshipPersonPayload);

    $flat = [
        'id' => $recordId,
    ];
    $branch = trim((string) ($record['cabang'] ?? ''));
    if ($branch !== '') {
        $flat['cabang'] = $branch;
    }
    foreach (['full_name', 'gender', 'birth_date', 'birth_day_month', 'whatsapp', 'birth_place', 'address', 'email', 'photos'] as $key) {
        if (array_key_exists($key, $profile)) {
            people_registry_copy_present_value($flat, $key, $profile[$key]);
        }
    }

    if ($memberPayload !== null) {
        $flat['membership_status'] = normalize_member_status_value((string) ($memberPayload['membership_status'] ?? 'active'));
        foreach (['social_media', 'family_ids', 'left_reason', 'left_at'] as $key) {
            if (array_key_exists($key, $memberPayload)) {
                people_registry_copy_present_value($flat, $key, $memberPayload[$key]);
            }
        }
    }

    if ($mskPayload !== null) {
        $flat['msk_month'] = normalize_month_value((string) ($mskPayload['msk_month'] ?? date('Y-m')));
        $flat['msk_session_numbers'] = normalize_msk_session_numbers($mskPayload['session_numbers'] ?? []);
        people_registry_copy_present_value($flat, 'msk_notes', $mskPayload['notes'] ?? '');
        people_registry_copy_present_value($flat, 'msk_completed_at', $mskPayload['completed_at'] ?? '');
        $journeyBridgeStatus = normalize_journey_bridge_status((string) ($mskPayload['journey_bridge_status'] ?? 'belum'));
        if ($journeyBridgeStatus !== 'belum') {
            $flat['msk_journey_bridge_status'] = $journeyBridgeStatus;
        }
        $mskStatus = normalize_msk_participant_status((string) ($mskPayload['status'] ?? 'active'));
        if ($mskStatus !== 'active') {
            $flat['msk_status'] = $mskStatus;
        }
    }

    if ($discipleshipPayload !== null) {
        $legacyPersonId = trim((string) ($discipleshipPayload['person_id'] ?? ''));
        $flat['legacy_dg_person_id'] = $legacyPersonId !== '' ? $legacyPersonId : $recordId;
        $flat['legacy_dg_role'] = trim((string) ($discipleshipPayload['role'] ?? 'Anggota')) !== '' ? trim((string) ($discipleshipPayload['role'] ?? 'Anggota')) : 'Anggota';
        if (is_array($discipleshipPayload['parent_ids'] ?? null) && count($discipleshipPayload['parent_ids']) > 0) {
            $flat['legacy_dg_parent_ids'] = array_values($discipleshipPayload['parent_ids']);
        }
        foreach ([
            'notes' => 'legacy_dg_notes',
            'kampus' => 'legacy_dg_kampus',
            'jurusan' => 'legacy_dg_jurusan',
            'pekerjaan' => 'legacy_dg_pekerjaan',
            'angkatan' => 'legacy_dg_angkatan',
            'created_at' => 'legacy_dg_created_at',
            'updated_at' => 'legacy_dg_updated_at',
        ] as $payloadKey => $flatKey) {
            if (array_key_exists($payloadKey, $discipleshipPayload)) {
                people_registry_copy_present_value($flat, $flatKey, $discipleshipPayload[$payloadKey]);
            }
        }
    }

    if ($discipleshipPersonPayload !== null) {
        $dgPersonId = trim((string) ($discipleshipPersonPayload['person_id'] ?? ''));
        $flat['dg_person_id'] = $dgPersonId !== '' ? $dgPersonId : $recordId;
        $dgMemberRef = trim((string) ($discipleshipPersonPayload['member_id'] ?? ''));
        if ($dgMemberRef !== '' && $dgMemberRef !== $recordId) {
            $flat['dg_member_ref'] = $dgMemberRef;
        }
        $flat['dg_status'] = trim((string) ($discipleshipPersonPayload['status'] ?? 'active')) !== '' ? trim((string) ($discipleshipPersonPayload['status'] ?? 'active')) : 'active';
        foreach ([
            'notes' => 'dg_notes',
            'kampus' => 'dg_kampus',
            'jurusan' => 'dg_jurusan',
            'pekerjaan' => 'dg_pekerjaan',
            'created_at' => 'dg_created_at',
            'updated_at' => 'dg_updated_at',
        ] as $payloadKey => $flatKey) {
            if (array_key_exists($payloadKey, $discipleshipPersonPayload)) {
                people_registry_copy_present_value($flat, $flatKey, $discipleshipPersonPayload[$payloadKey]);
            }
        }
    }

    $flat['created_at'] = (string) ($record['created_at'] ?? now_iso());
    $flat['updated_at'] = (string) ($record['updated_at'] ?? ($record['created_at'] ?? now_iso()));
    return $flat;
}
