<?php

function auto_register_msk_participant_as_member(array &$participant, array &$members): bool {
    $changed = false;
    if (normalize_msk_participant_status((string) ($participant['status'] ?? 'active')) !== 'active') {
        return false;
    }

    $memberId = trim((string) ($participant['member_id'] ?? ''));

    $sessions = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
    if (($participant['session_numbers'] ?? null) !== $sessions) {
        $participant['session_numbers'] = $sessions;
        $changed = true;
    }

    $participantPhotos = extract_msk_participant_photos($participant);
    if (($participant['photos'] ?? null) !== $participantPhotos || array_key_exists('photo_path', $participant) || array_key_exists('photo_name', $participant)) {
        $participant['photos'] = $participantPhotos;
        $changed = true;
    }

    $membersById = index_by_id($members);
    if ($memberId !== '' && isset($membersById[$memberId])) {
        $memberName = trim((string) ($membersById[$memberId]['full_name'] ?? ''));
        if ($memberName !== '' && trim((string) ($participant['full_name'] ?? '')) === '') {
            $participant['full_name'] = $memberName;
            $changed = true;
        }
    } elseif ($memberId !== '') {
        $participant['member_id'] = '';
        $memberId = '';
        $changed = true;
    }

    if (count($sessions) === 12 && trim((string) ($participant['completed_at'] ?? '')) === '') {
        $participant['completed_at'] = now_iso();
        $changed = true;
    }

    if (count($sessions) !== 12 || $memberId !== '') {
        return $changed;
    }

    $fullName = trim((string) ($participant['full_name'] ?? ''));
    if ($fullName === '') {
        return $changed;
    }

    $existingMemberId = find_unique_member_id_by_full_name($members, $fullName);
    if ($existingMemberId !== '') {
        $participant['member_id'] = $existingMemberId;
        return true;
    }
    if (has_member_by_full_name($members, $fullName)) {
        return $changed;
    }

    $gender = normalize_member_gender_value((string) ($participant['gender'] ?? ''));
    if ((string) ($participant['gender'] ?? '') !== $gender) {
        $participant['gender'] = $gender;
        $changed = true;
    }

    $birthDate = normalize_ymd_date((string) ($participant['birth_date'] ?? ''));
    $birthDayMonth = normalize_member_birth_day_month_value((string) ($participant['birth_day_month'] ?? ''));
    if ($birthDate !== '') {
        $timestamp = strtotime($birthDate);
        if ($timestamp !== false) {
            $birthDayMonth = date('d-m', $timestamp);
        }
    }
    $birthPlace = trim((string) ($participant['birth_place'] ?? ''));
    $address = trim((string) ($participant['address'] ?? ''));
    $email = strtolower(trim((string) ($participant['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = '';
    }
    $whatsapp = trim((string) ($participant['whatsapp'] ?? ''));
    $now = now_iso();
    $newMemberId = generate_id('member');
    $members[] = [
        'id' => $newMemberId,
        'full_name' => $fullName,
        'gender' => $gender,
        'birth_date' => $birthDate,
        'birth_day_month' => $birthDayMonth,
        'whatsapp' => $whatsapp,
        'birth_place' => $birthPlace,
        'address' => $address,
        'email' => $email,
        'social_media' => '',
        'membership_status' => 'active',
        'left_reason' => '',
        'left_at' => '',
        'photos' => $participantPhotos,
        'family_ids' => [],
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $participant['member_id'] = $newMemberId;
    if (trim((string) ($participant['completed_at'] ?? '')) === '') {
        $participant['completed_at'] = $now;
    }
    return true;
}
