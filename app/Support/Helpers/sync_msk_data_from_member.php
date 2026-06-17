<?php

function sync_msk_data_from_member(array $member, array &$mskClasses): bool {
    $memberId = trim((string) ($member['id'] ?? ''));
    if ($memberId === '') {
        return false;
    }

    $fullName = trim((string) ($member['full_name'] ?? ''));
    if ($fullName === '') {
        $fullName = 'Tanpa Nama';
    }
    $gender = normalize_member_gender_value((string) ($member['gender'] ?? ''));
    $birthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
    $birthDayMonth = normalize_member_birth_day_month_value((string) ($member['birth_day_month'] ?? ''));
    if ($birthDate !== '') {
        $timestamp = strtotime($birthDate);
        if ($timestamp !== false) {
            $birthDayMonth = date('d-m', $timestamp);
        }
    }
    $whatsapp = trim((string) ($member['whatsapp'] ?? ''));
    $birthPlace = trim((string) ($member['birth_place'] ?? ''));
    $address = trim((string) ($member['address'] ?? ''));
    $email = strtolower(trim((string) ($member['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = '';
    }
    $photos = extract_member_photos($member);

    $changed = false;
    foreach ($mskClasses as &$participant) {
        $linkedMemberId = trim((string) ($participant['member_id'] ?? ''));
        if ($linkedMemberId !== $memberId) {
            continue;
        }

        $participantChanged = false;
        if ((string) ($participant['full_name'] ?? '') !== $fullName) {
            $participant['full_name'] = $fullName;
            $participantChanged = true;
        }
        if ((string) ($participant['gender'] ?? '') !== $gender) {
            $participant['gender'] = $gender;
            $participantChanged = true;
        }
        if ((string) ($participant['birth_date'] ?? '') !== $birthDate) {
            $participant['birth_date'] = $birthDate;
            $participantChanged = true;
        }
        if ((string) ($participant['birth_day_month'] ?? '') !== $birthDayMonth) {
            $participant['birth_day_month'] = $birthDayMonth;
            $participantChanged = true;
        }
        if ((string) ($participant['whatsapp'] ?? '') !== $whatsapp) {
            $participant['whatsapp'] = $whatsapp;
            $participantChanged = true;
        }
        if ((string) ($participant['birth_place'] ?? '') !== $birthPlace) {
            $participant['birth_place'] = $birthPlace;
            $participantChanged = true;
        }
        if ((string) ($participant['address'] ?? '') !== $address) {
            $participant['address'] = $address;
            $participantChanged = true;
        }
        if ((string) ($participant['email'] ?? '') !== $email) {
            $participant['email'] = $email;
            $participantChanged = true;
        }
        if (array_key_exists('origin_church', $participant) || array_key_exists('gereja_asal', $participant)) {
            unset($participant['origin_church'], $participant['gereja_asal']);
            $participantChanged = true;
        }
        if (array_key_exists('origin_church_address', $participant) || array_key_exists('alamat_gereja', $participant)) {
            unset($participant['origin_church_address'], $participant['alamat_gereja']);
            $participantChanged = true;
        }
        if (($participant['photos'] ?? null) !== $photos || array_key_exists('photo_path', $participant) || array_key_exists('photo_name', $participant)) {
            $participant['photos'] = $photos;
            $participantChanged = true;
        }

        if ($participantChanged) {
            $participant['updated_at'] = now_iso();
            if (!isset($participant['created_at'])) {
                $participant['created_at'] = $participant['updated_at'];
            }
            $changed = true;
        }
    }
    unset($participant);

    return $changed;
}
