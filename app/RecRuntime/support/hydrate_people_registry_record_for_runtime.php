<?php

function hydrate_people_registry_record_for_runtime(array $record): array {
    if (people_registry_record_has_nested_payload($record)) {
        return $record;
    }

    $recordId = trim((string) ($record['id'] ?? ''));
    $createdAt = normalize_iso_datetime_to_jakarta((string) ($record['created_at'] ?? ''));
    if ($createdAt === '') {
        $createdAt = now_iso();
    }
    $updatedAt = normalize_iso_datetime_to_jakarta((string) ($record['updated_at'] ?? ''));
    if ($updatedAt === '') {
        $updatedAt = $createdAt;
    }

    $profile = [];
    foreach (['full_name', 'gender', 'birth_date', 'birth_day_month', 'whatsapp', 'birth_place', 'address', 'email', 'photos'] as $key) {
        if (array_key_exists($key, $record)) {
            $profile[$key] = $record[$key];
        }
    }

    $hydrated = [
        'id' => $recordId,
        'profile' => $profile,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ];
    $branch = trim((string) ($record['cabang'] ?? ''));
    if ($branch !== '') {
        $hydrated['cabang'] = $branch;
    }

    $hasMember = people_registry_has_any_present_key($record, [
        'membership_status',
        'social_media',
        'family_ids',
        'left_reason',
        'left_at',
    ]);
    if ($hasMember) {
        $member = [
            'member_id' => $recordId,
            'membership_status' => (string) ($record['membership_status'] ?? 'active'),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
        foreach (['social_media', 'family_ids', 'left_reason', 'left_at'] as $key) {
            if (array_key_exists($key, $record)) {
                $member[$key] = $record[$key];
            }
        }
        $hydrated['member'] = $member;
    }

    $hasMsk = people_registry_has_any_present_key($record, [
        'msk_month',
        'msk_session_numbers',
        'msk_notes',
        'msk_completed_at',
        'msk_journey_bridge_status',
        'msk_status',
    ]);
    if ($hasMsk) {
        $mskMemberId = ($recordId !== '' && (strpos($recordId, 'member_') === 0 || $hasMember)) ? $recordId : '';
        $msk = [
            'participant_id' => $recordId,
            'member_id' => $mskMemberId,
            'msk_month' => (string) ($record['msk_month'] ?? ''),
            'session_numbers' => $record['msk_session_numbers'] ?? [],
            'notes' => (string) ($record['msk_notes'] ?? ''),
            'completed_at' => (string) ($record['msk_completed_at'] ?? ''),
            'journey_bridge_status' => (string) ($record['msk_journey_bridge_status'] ?? 'belum'),
            'status' => (string) ($record['msk_status'] ?? 'active'),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
        $hydrated['msk'] = $msk;
    }

    $hasLegacyDg = people_registry_has_any_present_key($record, [
        'legacy_dg_person_id',
        'legacy_dg_role',
        'legacy_dg_parent_ids',
        'legacy_dg_notes',
        'legacy_dg_kampus',
        'legacy_dg_jurusan',
        'legacy_dg_pekerjaan',
        'legacy_dg_angkatan',
    ]);
    if ($hasLegacyDg) {
        $hydrated['discipleship'] = [
            'person_id' => trim((string) ($record['legacy_dg_person_id'] ?? '')) !== '' ? trim((string) ($record['legacy_dg_person_id'] ?? '')) : $recordId,
            'member_id' => $recordId,
            'name' => (string) ($record['full_name'] ?? ''),
            'phone' => (string) ($record['whatsapp'] ?? ''),
            'role' => (string) ($record['legacy_dg_role'] ?? 'Anggota'),
            'parent_ids' => is_array($record['legacy_dg_parent_ids'] ?? null) ? $record['legacy_dg_parent_ids'] : [],
            'notes' => (string) ($record['legacy_dg_notes'] ?? ''),
            'kampus' => (string) ($record['legacy_dg_kampus'] ?? ''),
            'jurusan' => (string) ($record['legacy_dg_jurusan'] ?? ''),
            'pekerjaan' => (string) ($record['legacy_dg_pekerjaan'] ?? ''),
            'angkatan' => (string) ($record['legacy_dg_angkatan'] ?? ''),
            'created_at' => (string) ($record['legacy_dg_created_at'] ?? $createdAt),
            'updated_at' => (string) ($record['legacy_dg_updated_at'] ?? $updatedAt),
        ];
    }

    $hasDg = people_registry_has_any_present_key($record, [
        'dg_person_id',
        'dg_member_ref',
        'dg_status',
        'dg_notes',
        'dg_kampus',
        'dg_jurusan',
        'dg_pekerjaan',
        'dg_relations',
    ]);
    if ($hasDg) {
        $defaultDgMemberRef = ($recordId !== '' && (strpos($recordId, 'member_') === 0 || $hasMember)) ? $recordId : '';
        $hydrated['discipleship_person'] = [
            'person_id' => trim((string) ($record['dg_person_id'] ?? '')) !== '' ? trim((string) ($record['dg_person_id'] ?? '')) : $recordId,
            'member_id' => trim((string) ($record['dg_member_ref'] ?? '')) !== '' ? trim((string) ($record['dg_member_ref'] ?? '')) : $defaultDgMemberRef,
            'full_name' => (string) ($record['full_name'] ?? ''),
            'phone' => (string) ($record['whatsapp'] ?? ''),
            'gender' => (string) ($record['gender'] ?? ''),
            'status' => (string) ($record['dg_status'] ?? 'active'),
            'notes' => (string) ($record['dg_notes'] ?? ''),
            'kampus' => (string) ($record['dg_kampus'] ?? ''),
            'jurusan' => (string) ($record['dg_jurusan'] ?? ''),
            'pekerjaan' => (string) ($record['dg_pekerjaan'] ?? ''),
            'created_at' => (string) ($record['dg_created_at'] ?? $createdAt),
            'updated_at' => (string) ($record['dg_updated_at'] ?? $updatedAt),
        ];
        if (is_array($record['dg_relations'] ?? null)) {
            $hydrated['discipleship_person']['relations'] = $record['dg_relations'];
        }
    }

    return $hydrated;
}
