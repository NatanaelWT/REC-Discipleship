<?php

function sync_member_data_from_msk(array &$participant, array &$members): bool {
    if (normalize_msk_participant_status((string) ($participant['status'] ?? 'active')) !== 'active') {
        return false;
    }
    $memberId = trim((string) ($participant['member_id'] ?? ''));
    if ($memberId === '') {
        return false;
    }

    $memberIndex = null;
    foreach ($members as $idx => $member) {
        if ((string) ($member['id'] ?? '') === $memberId) {
            $memberIndex = $idx;
            break;
        }
    }

    if ($memberIndex === null) {
        $participant['member_id'] = '';
        return true;
    }

    $changed = false;
    $memberChanged = false;
    $member = $members[$memberIndex];

    $participantName = trim((string) ($participant['full_name'] ?? ''));
    $memberName = trim((string) ($member['full_name'] ?? ''));
    $finalName = $participantName !== '' ? $participantName : $memberName;
    if ($finalName === '') {
        $finalName = 'Tanpa Nama';
    }

    $participantGender = normalize_member_gender_value((string) ($participant['gender'] ?? ''));
    $memberGender = normalize_member_gender_value((string) ($member['gender'] ?? ''));
    $finalGender = $participantGender !== '' ? $participantGender : $memberGender;

    $participantBirthDate = normalize_ymd_date((string) ($participant['birth_date'] ?? ''));
    $memberBirthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
    $finalBirthDate = $participantBirthDate !== '' ? $participantBirthDate : $memberBirthDate;

    $participantBirthDayMonth = normalize_member_birth_day_month_value((string) ($participant['birth_day_month'] ?? ''));
    $memberBirthDayMonth = normalize_member_birth_day_month_value((string) ($member['birth_day_month'] ?? ''));
    $finalBirthDayMonth = '';
    if ($finalBirthDate !== '') {
        $timestamp = strtotime($finalBirthDate);
        if ($timestamp !== false) {
            $finalBirthDayMonth = date('d-m', $timestamp);
        }
    } else {
        $finalBirthDayMonth = $participantBirthDayMonth !== '' ? $participantBirthDayMonth : $memberBirthDayMonth;
    }

    $participantWhatsapp = trim((string) ($participant['whatsapp'] ?? ''));
    $memberWhatsapp = trim((string) ($member['whatsapp'] ?? ''));
    $finalWhatsapp = $participantWhatsapp !== '' ? $participantWhatsapp : $memberWhatsapp;

    $participantBirthPlace = trim((string) ($participant['birth_place'] ?? ''));
    $memberBirthPlace = trim((string) ($member['birth_place'] ?? ''));
    $finalBirthPlace = $participantBirthPlace !== '' ? $participantBirthPlace : $memberBirthPlace;

    $participantAddress = trim((string) ($participant['address'] ?? ''));
    $memberAddress = trim((string) ($member['address'] ?? ''));
    $finalAddress = $participantAddress !== '' ? $participantAddress : $memberAddress;

    $participantEmail = strtolower(trim((string) ($participant['email'] ?? '')));
    if ($participantEmail !== '' && filter_var($participantEmail, FILTER_VALIDATE_EMAIL) === false) {
        $participantEmail = '';
    }
    $memberEmail = strtolower(trim((string) ($member['email'] ?? '')));
    if ($memberEmail !== '' && filter_var($memberEmail, FILTER_VALIDATE_EMAIL) === false) {
        $memberEmail = '';
    }
    $finalEmail = $participantEmail !== '' ? $participantEmail : $memberEmail;

    $participantPhotos = extract_msk_participant_photos($participant);
    $memberPhotos = extract_member_photos($member);
    $finalPhotos = count($participantPhotos) > 0 ? $participantPhotos : $memberPhotos;

    if ((string) ($participant['full_name'] ?? '') !== $finalName) {
        $participant['full_name'] = $finalName;
        $changed = true;
    }
    if ((string) ($participant['gender'] ?? '') !== $finalGender) {
        $participant['gender'] = $finalGender;
        $changed = true;
    }
    if ((string) ($participant['birth_date'] ?? '') !== $finalBirthDate) {
        $participant['birth_date'] = $finalBirthDate;
        $changed = true;
    }
    if ((string) ($participant['birth_day_month'] ?? '') !== $finalBirthDayMonth) {
        $participant['birth_day_month'] = $finalBirthDayMonth;
        $changed = true;
    }
    if ((string) ($participant['whatsapp'] ?? '') !== $finalWhatsapp) {
        $participant['whatsapp'] = $finalWhatsapp;
        $changed = true;
    }
    if ((string) ($participant['birth_place'] ?? '') !== $finalBirthPlace) {
        $participant['birth_place'] = $finalBirthPlace;
        $changed = true;
    }
    if ((string) ($participant['address'] ?? '') !== $finalAddress) {
        $participant['address'] = $finalAddress;
        $changed = true;
    }
    if ((string) ($participant['email'] ?? '') !== $finalEmail) {
        $participant['email'] = $finalEmail;
        $changed = true;
    }
    if (array_key_exists('origin_church', $participant) || array_key_exists('gereja_asal', $participant)) {
        unset($participant['origin_church'], $participant['gereja_asal']);
        $changed = true;
    }
    if (array_key_exists('origin_church_address', $participant) || array_key_exists('alamat_gereja', $participant)) {
        unset($participant['origin_church_address'], $participant['alamat_gereja']);
        $changed = true;
    }
    if (($participant['photos'] ?? null) !== $finalPhotos || array_key_exists('photo_path', $participant) || array_key_exists('photo_name', $participant)) {
        $participant['photos'] = $finalPhotos;
        $changed = true;
    }

    if ((string) ($member['full_name'] ?? '') !== $finalName) {
        $member['full_name'] = $finalName;
        $memberChanged = true;
    }
    if ((string) ($member['gender'] ?? '') !== $finalGender) {
        $member['gender'] = $finalGender;
        $memberChanged = true;
    }
    if ((string) ($member['birth_date'] ?? '') !== $finalBirthDate) {
        $member['birth_date'] = $finalBirthDate;
        $memberChanged = true;
    }
    if ((string) ($member['birth_day_month'] ?? '') !== $finalBirthDayMonth) {
        $member['birth_day_month'] = $finalBirthDayMonth;
        $memberChanged = true;
    }
    if ((string) ($member['whatsapp'] ?? '') !== $finalWhatsapp) {
        $member['whatsapp'] = $finalWhatsapp;
        $memberChanged = true;
    }
    if ((string) ($member['birth_place'] ?? '') !== $finalBirthPlace) {
        $member['birth_place'] = $finalBirthPlace;
        $memberChanged = true;
    }
    if ((string) ($member['address'] ?? '') !== $finalAddress) {
        $member['address'] = $finalAddress;
        $memberChanged = true;
    }
    if ((string) ($member['email'] ?? '') !== $finalEmail) {
        $member['email'] = $finalEmail;
        $memberChanged = true;
    }
    if (array_key_exists('origin_church', $member) || array_key_exists('gereja_asal', $member)) {
        unset($member['origin_church'], $member['gereja_asal']);
        $memberChanged = true;
    }
    if (array_key_exists('origin_church_address', $member) || array_key_exists('alamat_gereja', $member)) {
        unset($member['origin_church_address'], $member['alamat_gereja']);
        $memberChanged = true;
    }
    if (($member['photos'] ?? null) !== $finalPhotos || array_key_exists('photo_path', $member) || array_key_exists('photo_name', $member)) {
        $member['photos'] = $finalPhotos;
        $memberChanged = true;
    }

    if ($memberChanged) {
        $member['updated_at'] = now_iso();
        if (!isset($member['created_at'])) {
            $member['created_at'] = $member['updated_at'];
        }
        $members[$memberIndex] = $member;
        $changed = true;
    }

    return $changed;
}
