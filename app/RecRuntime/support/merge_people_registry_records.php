<?php

function merge_people_registry_records(array $baseRecords, array $members, array $mskClasses, array $people = []): array {
    $baseById = [];
    foreach (normalize_people_registry_records($baseRecords) as $record) {
        $recordId = trim((string) ($record['id'] ?? ''));
        if ($recordId === '') {
            continue;
        }
        $baseById[$recordId] = $record;
    }

    $mergedById = [];

    foreach ($members as $member) {
        if (!is_array($member)) {
            continue;
        }
        $memberId = trim((string) ($member['id'] ?? ''));
        if ($memberId === '') {
            continue;
        }

        $existing = $mergedById[$memberId] ?? ($baseById[$memberId] ?? [
            'id' => $memberId,
            'profile' => [],
            'is_member' => false,
            'is_participant' => false,
            'member' => null,
            'msk' => null,
            'discipleship' => null,
            'discipleship_person' => null,
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ]);
        $existingProfile = is_array($existing['profile'] ?? null) ? $existing['profile'] : [];
        $existingMember = is_array($existing['member'] ?? null) ? $existing['member'] : [];

        $existing['id'] = $memberId;
        $existing['profile'] = unified_person_profile($member, $existingProfile);
        $existing['member'] = unified_member_payload($member, $memberId, $existingMember);
        // Always reset MSK payload from member side.
        // Current MSK state must come only from `$mskClasses` loop below,
        // so deleted MSK participants are not resurrected from base records.
        $existing['msk'] = null;
        if (!isset($existing['discipleship']) || !is_array($existing['discipleship'])) {
            $existing['discipleship'] = null;
        }
        if (!isset($existing['discipleship_person']) || !is_array($existing['discipleship_person'])) {
            $existing['discipleship_person'] = null;
        }
        $existing['is_member'] = true;
        $existing['is_participant'] = false;
        $mergedById[$memberId] = sync_unified_record_timestamps($existing);
    }

    foreach ($mskClasses as $participant) {
        if (!is_array($participant)) {
            continue;
        }
        $participantId = trim((string) ($participant['id'] ?? ''));
        $linkedMemberId = trim((string) ($participant['member_id'] ?? ''));
        $recordId = $linkedMemberId !== '' ? $linkedMemberId : $participantId;
        if ($recordId === '') {
            continue;
        }

        $existing = $mergedById[$recordId] ?? ($baseById[$recordId] ?? [
            'id' => $recordId,
            'profile' => [],
            'is_member' => false,
            'is_participant' => false,
            'member' => null,
            'msk' => null,
            'discipleship' => null,
            'discipleship_person' => null,
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ]);
        $existingProfile = is_array($existing['profile'] ?? null) ? $existing['profile'] : [];
        $existingMsk = is_array($existing['msk'] ?? null) ? $existing['msk'] : [];
        $existingMemberId = is_array($existing['member'] ?? null) ? trim((string) ($existing['member']['member_id'] ?? '')) : '';

        $profileFromParticipant = unified_person_profile($participant, $existingProfile);
        if ($existingMemberId !== '' && $linkedMemberId !== '') {
            $existing['profile'] = unified_person_profile($existingProfile, $profileFromParticipant);
        } else {
            $existing['profile'] = $profileFromParticipant;
        }

        $defaultMskMemberId = $linkedMemberId !== '' ? $linkedMemberId : $existingMemberId;
        $existing['msk'] = unified_msk_payload($participant, $recordId, $defaultMskMemberId, $existingMsk);

        $existing['id'] = $recordId;
        $existing['is_member'] = is_array($existing['member']);
        $existing['is_participant'] = true;
        if (!isset($existing['discipleship']) || !is_array($existing['discipleship'])) {
            $existing['discipleship'] = null;
        }
        if (!isset($existing['discipleship_person']) || !is_array($existing['discipleship_person'])) {
            $existing['discipleship_person'] = null;
        }
        $mergedById[$recordId] = sync_unified_record_timestamps($existing);
    }

    foreach ($people as $person) {
        if (!is_array($person)) {
            continue;
        }
        $personId = trim((string) ($person['id'] ?? $person['person_id'] ?? ''));
        $memberId = trim((string) ($person['member_id'] ?? ''));
        $recordId = $memberId !== '' ? $memberId : $personId;
        if ($recordId === '') {
            continue;
        }

        $existing = $mergedById[$recordId] ?? ($baseById[$recordId] ?? [
            'id' => $recordId,
            'profile' => [],
            'is_member' => false,
            'is_participant' => false,
            'member' => null,
            'msk' => null,
            'discipleship' => null,
            'discipleship_person' => null,
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ]);
        $existingProfile = is_array($existing['profile'] ?? null) ? $existing['profile'] : [];
        $existingDiscipleship = is_array($existing['discipleship'] ?? null) ? $existing['discipleship'] : [];
        $fallback = array_merge($existingDiscipleship, [
            'person_id' => $personId,
            'member_id' => $memberId,
            'name' => (string) ($existingProfile['full_name'] ?? ''),
            'phone' => (string) ($existingProfile['whatsapp'] ?? ''),
        ]);

        $existing['id'] = $recordId;
        $existing['discipleship'] = unified_discipleship_payload($person, $fallback);
        $profileFromPerson = unified_person_profile([
            'full_name' => (string) ($person['name'] ?? ''),
            'whatsapp' => (string) ($person['phone'] ?? ''),
        ], $existingProfile);
        $existing['profile'] = unified_person_profile($existingProfile, $profileFromPerson);
        $existing['is_member'] = is_array($existing['member'] ?? null);
        $existing['is_participant'] = is_array($existing['msk'] ?? null);
        $mergedById[$recordId] = sync_unified_record_timestamps($existing);
    }

    foreach ($baseById as $recordId => $record) {
        if (isset($mergedById[$recordId])) {
            continue;
        }
        if (
            (is_array($record['discipleship'] ?? null) || is_array($record['discipleship_person'] ?? null))
            && !is_array($record['member'] ?? null)
            && !is_array($record['msk'] ?? null)
        ) {
            $mergedById[$recordId] = sync_unified_record_timestamps($record);
        }
    }

    return array_values($mergedById);
}
