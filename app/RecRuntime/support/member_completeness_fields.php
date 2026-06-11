<?php

function member_completeness_fields(array $member): array {
    $fullName = trim((string) ($member['full_name'] ?? ''));
    $gender = normalize_member_gender_value((string) ($member['gender'] ?? ''));
    $birthPlace = trim((string) ($member['birth_place'] ?? ''));
    $birthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
    $birthDayMonth = normalize_member_birth_day_month_value((string) ($member['birth_day_month'] ?? ''));
    $address = trim((string) ($member['address'] ?? ''));
    $whatsapp = trim((string) ($member['whatsapp'] ?? ''));
    $whatsappDigits = normalize_whatsapp_digits($whatsapp);
    $email = strtolower(trim((string) ($member['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = '';
    }
    $socialMedia = normalize_social_link_value((string) ($member['social_media'] ?? ''));
    $familyIdsInput = $member['family_ids'] ?? [];
    if (!is_array($familyIdsInput)) {
        $familyIdsInput = [];
    }
    $familyIds = [];
    foreach ($familyIdsInput as $familyIdRaw) {
        $familyId = trim((string) $familyIdRaw);
        if ($familyId === '' || isset($familyIds[$familyId])) {
            continue;
        }
        $familyIds[$familyId] = true;
    }
    $photos = extract_member_photos($member);

    return [
        'full_name' => ['label' => 'Nama Lengkap', 'filled' => $fullName !== ''],
        'gender' => ['label' => 'Jenis Kelamin', 'filled' => $gender !== ''],
        'birth_place' => ['label' => 'Tempat Lahir', 'filled' => $birthPlace !== ''],
        'birth' => ['label' => 'Tanggal Lahir', 'filled' => $birthDate !== '' || $birthDayMonth !== ''],
        'birth_year' => ['label' => 'Tahun Lahir', 'filled' => $birthDate !== ''],
        'address' => ['label' => 'Alamat', 'filled' => $address !== ''],
        'whatsapp' => ['label' => 'WhatsApp', 'filled' => $whatsappDigits !== ''],
        'email' => ['label' => 'Email', 'filled' => $email !== ''],
        'social_media' => ['label' => 'Sosial Media', 'filled' => $socialMedia !== ''],
        'photos' => ['label' => 'Foto', 'filled' => count($photos) > 0],
        'family_ids' => ['label' => 'Relasi Keluarga', 'filled' => count($familyIds) > 0],
    ];
}
