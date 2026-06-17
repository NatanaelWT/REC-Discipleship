<?php

function unified_person_profile(array $source, array $fallback = []): array {
    $fullName = unified_pick_string($source, $fallback, ['full_name', 'name'], '');
    $gender = normalize_member_gender_value(unified_pick_string($source, $fallback, ['gender'], ''));

    $birthDate = normalize_ymd_date(unified_pick_string($source, $fallback, ['birth_date', 'tanggal_lahir'], ''));
    $birthDayMonth = normalize_member_birth_day_month_value(unified_pick_string($source, $fallback, ['birth_day_month', 'tanggal_bulan_lahir'], ''));
    if ($birthDate !== '') {
        $timestamp = strtotime($birthDate);
        if ($timestamp !== false) {
            $birthDayMonth = date('d-m', $timestamp);
        }
    }

    $whatsapp = unified_pick_string($source, $fallback, ['whatsapp', 'phone'], '');
    $birthPlace = unified_pick_string($source, $fallback, ['birth_place', 'tempat_lahir'], '');
    $address = unified_pick_string($source, $fallback, ['address', 'alamat'], '');

    $email = strtolower(unified_pick_string($source, $fallback, ['email'], ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = '';
    }

    $photoSource = null;
    if (array_key_exists('photos', $source) || array_key_exists('photo_path', $source) || array_key_exists('photo_name', $source)) {
        $photoSource = $source;
    } elseif (array_key_exists('photos', $fallback) || array_key_exists('photo_path', $fallback) || array_key_exists('photo_name', $fallback)) {
        $photoSource = $fallback;
    }
    $photos = $photoSource !== null ? extract_member_photos($photoSource) : [];

    return [
        'full_name' => $fullName,
        'gender' => $gender,
        'birth_date' => $birthDate,
        'birth_day_month' => $birthDayMonth,
        'whatsapp' => $whatsapp,
        'birth_place' => $birthPlace,
        'address' => $address,
        'email' => $email,
        'photos' => $photos,
    ];
}
