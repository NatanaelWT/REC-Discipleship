<?php

function normalize_people_registry_records($records): array {
    if (!is_array($records)) {
        return [];
    }

    $normalizedById = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $record = hydrate_people_registry_record_for_runtime($record);

        $recordId = trim((string) ($record['id'] ?? ''));
        $recordBranch = trim((string) ($record['cabang'] ?? ''));
        $memberRaw = $record['member'] ?? null;
        if (!is_array($memberRaw)) {
            $memberRaw = null;
        }
        $mskRaw = $record['msk'] ?? null;
        if (!is_array($mskRaw)) {
            $mskRaw = null;
        }
        $discipleshipRaw = $record['discipleship'] ?? ($record['person'] ?? null);
        if (!is_array($discipleshipRaw)) {
            $discipleshipRaw = null;
        }
        $discipleshipPersonRaw = $record['discipleship_person'] ?? ($record['discipleship_v2_person'] ?? null);
        if (!is_array($discipleshipPersonRaw)) {
            $discipleshipPersonRaw = null;
        }
        $profileRaw = $record['profile'] ?? null;
        if (!is_array($profileRaw)) {
            $profileRaw = [];
        }

        $profileFallback = [];
        if ($memberRaw !== null) {
            $profileFallback = $memberRaw;
        }
        if ($mskRaw !== null) {
            foreach ($mskRaw as $key => $value) {
                if (!array_key_exists($key, $profileFallback)) {
                    $profileFallback[$key] = $value;
                }
            }
        }
        $profile = unified_person_profile($profileRaw, $profileFallback);

        $memberPayload = null;
        if ($memberRaw !== null) {
            $isMember = array_key_exists('is_member', $memberRaw) ? parse_bool_value($memberRaw['is_member']) : true;
            if ($isMember) {
                $memberPayload = unified_member_payload($memberRaw, $recordId);
            }
        }

        $defaultMemberId = $memberPayload['member_id'] ?? '';
        $mskPayload = null;
        if ($mskRaw !== null) {
            $isParticipant = array_key_exists('is_participant', $mskRaw) ? parse_bool_value($mskRaw['is_participant']) : true;
            if ($isParticipant) {
                $mskPayload = unified_msk_payload($mskRaw, $recordId, $defaultMemberId);
            }
        }
        $discipleshipPayload = null;
        if ($discipleshipRaw !== null) {
            $isPerson = array_key_exists('is_person', $discipleshipRaw) ? parse_bool_value($discipleshipRaw['is_person']) : true;
            if ($isPerson) {
                $discipleshipPayload = unified_discipleship_payload($discipleshipRaw, [
                    'person_id' => $recordId,
                    'member_id' => $recordId,
                    'name' => (string) ($profile['full_name'] ?? ''),
                    'phone' => (string) ($profile['whatsapp'] ?? ''),
                ]);
            }
        }
        $discipleshipPersonPayload = null;
        if ($discipleshipPersonRaw !== null) {
            $isPerson = array_key_exists('is_person', $discipleshipPersonRaw) ? parse_bool_value($discipleshipPersonRaw['is_person']) : true;
            if ($isPerson) {
                $discipleshipPersonPayload = unified_discipleship_person_payload($discipleshipPersonRaw, [
                    'person_id' => $recordId,
                    'member_id' => $defaultMemberId !== '' ? $defaultMemberId : '',
                    'full_name' => (string) ($profile['full_name'] ?? ''),
                    'phone' => (string) ($profile['whatsapp'] ?? ''),
                    'gender' => (string) ($profile['gender'] ?? ''),
                ]);
            }
        }

        if ($recordId === '') {
            if ($memberPayload !== null) {
                $recordId = trim((string) ($memberPayload['member_id'] ?? ''));
            }
            if ($recordId === '' && $mskPayload !== null) {
                $recordId = trim((string) ($mskPayload['member_id'] ?? ''));
                if ($recordId === '') {
                    $recordId = trim((string) ($mskPayload['participant_id'] ?? ''));
                }
            }
            if ($recordId === '' && $discipleshipPayload !== null) {
                $recordId = trim((string) ($discipleshipPayload['member_id'] ?? ''));
                if ($recordId === '') {
                    $recordId = trim((string) ($discipleshipPayload['person_id'] ?? ''));
                }
            }
            if ($recordId === '' && $discipleshipPersonPayload !== null) {
                $recordId = trim((string) ($discipleshipPersonPayload['member_id'] ?? ''));
                if ($recordId === '') {
                    $recordId = trim((string) ($discipleshipPersonPayload['person_id'] ?? ''));
                }
            }
            if ($recordId === '') {
                $recordId = generate_id('person');
            }
        }

        if ($memberPayload !== null && trim((string) ($memberPayload['member_id'] ?? '')) === '') {
            $memberPayload['member_id'] = $recordId;
        }
        if ($mskPayload !== null) {
            if (trim((string) ($mskPayload['participant_id'] ?? '')) === '') {
                $mskPayload['participant_id'] = generate_id('msk');
            }
        }
        if ($discipleshipPayload !== null) {
            if (trim((string) ($discipleshipPayload['member_id'] ?? '')) === '') {
                $discipleshipPayload['member_id'] = $recordId;
            }
            if (trim((string) ($discipleshipPayload['person_id'] ?? '')) === '') {
                $discipleshipPayload['person_id'] = generate_id('person');
            }
        }
        if ($discipleshipPersonPayload !== null) {
            if (trim((string) ($discipleshipPersonPayload['person_id'] ?? '')) === '') {
                $discipleshipPersonPayload['person_id'] = $recordId !== '' ? $recordId : generate_id('person');
            }
        }

        $createdAt = normalize_iso_datetime_to_jakarta(unified_pick_string($record, [], ['created_at'], ''));
        if ($createdAt === '') {
            $createdAt = first_iso_datetime([
                (string) ($memberPayload['created_at'] ?? ''),
                (string) ($mskPayload['created_at'] ?? ''),
                (string) ($discipleshipPayload['created_at'] ?? ''),
                (string) ($discipleshipPersonPayload['created_at'] ?? ''),
            ]);
            if ($createdAt === '') {
                $createdAt = now_iso();
            }
        }
        $updatedAt = normalize_iso_datetime_to_jakarta(unified_pick_string($record, [], ['updated_at'], ''));
        if ($updatedAt === '') {
            $updatedAt = latest_iso_datetime([
                (string) ($memberPayload['updated_at'] ?? ''),
                (string) ($mskPayload['updated_at'] ?? ''),
                (string) ($discipleshipPayload['updated_at'] ?? ''),
                (string) ($discipleshipPersonPayload['updated_at'] ?? ''),
                $createdAt,
            ]);
        }
        if ($updatedAt === '') {
            $updatedAt = $createdAt;
        }

        $normalizedKey = $recordBranch !== '' ? $recordBranch . '|' . $recordId : $recordId;
        if (isset($normalizedById[$normalizedKey])) {
            $existing = $normalizedById[$normalizedKey];
            if ($existing['member'] === null && $memberPayload !== null) {
                $existing['member'] = $memberPayload;
            }
            if ($existing['msk'] === null && $mskPayload !== null) {
                $existing['msk'] = $mskPayload;
            }
            if ($existing['discipleship'] === null && $discipleshipPayload !== null) {
                $existing['discipleship'] = $discipleshipPayload;
            } elseif ($discipleshipPayload !== null) {
                $existingDiscipleshipTs = strtotime((string) ($existing['discipleship']['updated_at'] ?? '')) ?: 0;
                $incomingDiscipleshipTs = strtotime((string) ($discipleshipPayload['updated_at'] ?? '')) ?: 0;
                if ($incomingDiscipleshipTs >= $existingDiscipleshipTs) {
                    $existing['discipleship'] = $discipleshipPayload;
                }
            }
            if (($existing['discipleship_person'] ?? null) === null && $discipleshipPersonPayload !== null) {
                $existing['discipleship_person'] = $discipleshipPersonPayload;
            } elseif ($discipleshipPersonPayload !== null) {
                $existingDiscipleshipPersonTs = strtotime((string) ($existing['discipleship_person']['updated_at'] ?? '')) ?: 0;
                $incomingDiscipleshipPersonTs = strtotime((string) ($discipleshipPersonPayload['updated_at'] ?? '')) ?: 0;
                if ($incomingDiscipleshipPersonTs >= $existingDiscipleshipPersonTs) {
                    $existing['discipleship_person'] = $discipleshipPersonPayload;
                }
            }
            if (($existing['profile']['full_name'] ?? '') === '' && ($profile['full_name'] ?? '') !== '') {
                $existing['profile'] = $profile;
            }
            if (trim((string) ($existing['cabang'] ?? '')) === '' && $recordBranch !== '') {
                $existing['cabang'] = $recordBranch;
            }
            $existingUpdatedTs = strtotime((string) ($existing['updated_at'] ?? '')) ?: 0;
            $incomingUpdatedTs = strtotime($updatedAt) ?: 0;
            if ($incomingUpdatedTs > $existingUpdatedTs) {
                $existing['updated_at'] = $updatedAt;
                if (($profile['full_name'] ?? '') !== '') {
                    $existing['profile'] = $profile;
                }
            }
            $existing['is_member'] = is_array($existing['member'] ?? null);
            $existing['is_participant'] = is_array($existing['msk'] ?? null);
            $normalizedById[$normalizedKey] = sync_unified_record_timestamps($existing);
            continue;
        }

        $normalizedById[$normalizedKey] = sync_unified_record_timestamps([
            'cabang' => $recordBranch,
            'id' => $recordId,
            'profile' => $profile,
            'is_member' => $memberPayload !== null,
            'is_participant' => $mskPayload !== null,
            'member' => $memberPayload,
            'msk' => $mskPayload,
            'discipleship' => $discipleshipPayload,
            'discipleship_person' => $discipleshipPersonPayload,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
    }

    return array_values($normalizedById);
}
