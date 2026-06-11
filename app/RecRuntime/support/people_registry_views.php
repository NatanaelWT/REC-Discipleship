<?php

function people_registry_views(array $records): array {
    $members = [];
    $mskClasses = [];
    $people = [];
    $peopleById = [];
    foreach (normalize_people_registry_records($records) as $record) {
        $recordId = trim((string) ($record['id'] ?? ''));
        $profile = is_array($record['profile'] ?? null) ? $record['profile'] : [];

        $memberPayload = $record['member'] ?? null;
        if (is_array($memberPayload) && (!array_key_exists('is_member', $memberPayload) || parse_bool_value($memberPayload['is_member']))) {
            $memberId = trim((string) ($memberPayload['member_id'] ?? $recordId));
            if ($memberId === '') {
                $memberId = $recordId !== '' ? $recordId : generate_id('member');
            }
            $membershipStatus = normalize_member_status_value((string) ($memberPayload['membership_status'] ?? 'active'));
            $leftReason = trim((string) ($memberPayload['left_reason'] ?? ''));
            $leftAt = trim((string) ($memberPayload['left_at'] ?? ''));
            if ($membershipStatus !== 'left') {
                $leftReason = '';
                $leftAt = '';
            } elseif ($leftAt === '') {
                $leftAt = (string) ($memberPayload['updated_at'] ?? now_iso());
            }

            $familyIdsInput = $memberPayload['family_ids'] ?? [];
            if (!is_array($familyIdsInput)) {
                $familyIdsInput = [];
            }
            $familyIds = [];
            foreach ($familyIdsInput as $familyId) {
                $familyId = trim((string) $familyId);
                if ($familyId === '' || $familyId === $memberId) {
                    continue;
                }
                $familyIds[] = $familyId;
            }
            $familyIds = array_values(array_unique($familyIds));

            $members[] = [
                'id' => $memberId,
                'full_name' => trim((string) ($profile['full_name'] ?? '')),
                'gender' => normalize_member_gender_value((string) ($profile['gender'] ?? '')),
                'birth_date' => normalize_ymd_date((string) ($profile['birth_date'] ?? '')),
                'birth_day_month' => normalize_member_birth_day_month_value((string) ($profile['birth_day_month'] ?? '')),
                'whatsapp' => trim((string) ($profile['whatsapp'] ?? '')),
                'birth_place' => trim((string) ($profile['birth_place'] ?? '')),
                'address' => trim((string) ($profile['address'] ?? '')),
                'email' => strtolower(trim((string) ($profile['email'] ?? ''))),
                'social_media' => normalize_social_link_value((string) ($memberPayload['social_media'] ?? '')),
                'membership_status' => $membershipStatus,
                'left_reason' => $leftReason,
                'left_at' => $leftAt,
                'photos' => extract_member_photos(['photos' => $profile['photos'] ?? []]),
                'family_ids' => $familyIds,
                'created_at' => (string) ($memberPayload['created_at'] ?? ($record['created_at'] ?? now_iso())),
                'updated_at' => (string) ($memberPayload['updated_at'] ?? ($record['updated_at'] ?? now_iso())),
            ];
        }

        $mskPayload = $record['msk'] ?? null;
        if (is_array($mskPayload) && (!array_key_exists('is_participant', $mskPayload) || parse_bool_value($mskPayload['is_participant']))) {
            $participantId = trim((string) ($mskPayload['participant_id'] ?? $recordId));
            if ($participantId === '') {
                $participantId = generate_id('msk');
            }
            $linkedMemberId = trim((string) ($mskPayload['member_id'] ?? ''));
            if ($linkedMemberId === '' && is_array($memberPayload)) {
                $linkedMemberId = trim((string) ($memberPayload['member_id'] ?? ''));
            }

            $mskClasses[] = [
                'id' => $participantId,
                'member_id' => $linkedMemberId,
                'full_name' => trim((string) ($profile['full_name'] ?? '')),
                'gender' => normalize_member_gender_value((string) ($profile['gender'] ?? '')),
                'birth_date' => normalize_ymd_date((string) ($profile['birth_date'] ?? '')),
                'birth_day_month' => normalize_member_birth_day_month_value((string) ($profile['birth_day_month'] ?? '')),
                'whatsapp' => trim((string) ($profile['whatsapp'] ?? '')),
                'birth_place' => trim((string) ($profile['birth_place'] ?? '')),
                'address' => trim((string) ($profile['address'] ?? '')),
                'email' => strtolower(trim((string) ($profile['email'] ?? ''))),
                'photos' => extract_member_photos(['photos' => $profile['photos'] ?? []]),
                'msk_month' => normalize_month_value((string) ($mskPayload['msk_month'] ?? date('Y-m'))),
                'session_numbers' => normalize_msk_session_numbers($mskPayload['session_numbers'] ?? []),
                'notes' => trim((string) ($mskPayload['notes'] ?? '')),
                'completed_at' => trim((string) ($mskPayload['completed_at'] ?? '')),
                'journey_bridge_status' => normalize_journey_bridge_status((string) ($mskPayload['journey_bridge_status'] ?? 'belum')),
                'status' => normalize_msk_participant_status((string) ($mskPayload['status'] ?? 'active')),
                'created_at' => (string) ($mskPayload['created_at'] ?? ($record['created_at'] ?? now_iso())),
                'updated_at' => (string) ($mskPayload['updated_at'] ?? ($record['updated_at'] ?? now_iso())),
            ];
        }

        $personPayload = $record['discipleship'] ?? null;
        if (is_array($personPayload) && (!array_key_exists('is_person', $personPayload) || parse_bool_value($personPayload['is_person']))) {
            $personId = trim((string) ($personPayload['person_id'] ?? ''));
            if ($personId === '') {
                $personId = $recordId !== '' ? $recordId : generate_id('person');
            }
            $personName = trim((string) ($personPayload['name'] ?? ''));
            if ($personName === '') {
                $personName = trim((string) ($profile['full_name'] ?? ''));
            }
            $personPhone = trim((string) ($personPayload['phone'] ?? ''));
            if ($personPhone === '') {
                $personPhone = trim((string) ($profile['whatsapp'] ?? ''));
            }
            $personMemberId = trim((string) ($personPayload['member_id'] ?? ''));
            if ($personMemberId === '') {
                $personMemberId = $recordId;
            }
            $parentIdsInput = $personPayload['parent_ids'] ?? [];
            if (!is_array($parentIdsInput)) {
                $parentIdsInput = [];
            }
            $parentIds = [];
            foreach ($parentIdsInput as $parentIdRaw) {
                $parentId = trim((string) $parentIdRaw);
                if ($parentId === '' || $parentId === $personId) {
                    continue;
                }
                $parentIds[] = $parentId;
            }
            $parentIds = array_values(array_unique($parentIds));

            $peopleById[$personId] = [
                'id' => $personId,
                'member_id' => $personMemberId,
                'name' => $personName,
                'phone' => $personPhone,
                'role' => trim((string) ($personPayload['role'] ?? 'Anggota')) !== '' ? trim((string) ($personPayload['role'] ?? 'Anggota')) : 'Anggota',
                'parent_ids' => $parentIds,
                'notes' => trim((string) ($personPayload['notes'] ?? '')),
                'kampus' => trim((string) ($personPayload['kampus'] ?? '')),
                'jurusan' => trim((string) ($personPayload['jurusan'] ?? '')),
                'pekerjaan' => trim((string) ($personPayload['pekerjaan'] ?? '')),
                'angkatan' => trim((string) ($personPayload['angkatan'] ?? '')),
                'created_at' => (string) ($personPayload['created_at'] ?? ($record['created_at'] ?? now_iso())),
                'updated_at' => (string) ($personPayload['updated_at'] ?? ($record['updated_at'] ?? now_iso())),
            ];
        }
    }

    $people = array_values($peopleById);

    return [
        'members' => array_values($members),
        'msk_classes' => array_values($mskClasses),
        'people' => $people,
    ];
}
