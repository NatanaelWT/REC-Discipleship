<?php

function build_msk_import_export_rows(array $participants): array
{
    $rows = [msk_import_export_header_row()];

    foreach ($participants as $participant) {
        if (! is_array($participant)) {
            continue;
        }

        $rows[] = build_msk_import_export_participant_row($participant);
    }

    return $rows;
}

function msk_import_export_header_row(): array
{
    return [
        'participant_id',
        'full_name',
        'whatsapp',
        'gender',
        'birth_date',
        'birth_place',
        'address',
        'email',
        'msk_month',
        'session_numbers',
        'notes',
    ];
}

function build_msk_import_export_participant_row(array $participant): array
{
    $gender = normalize_member_gender_value((string) ($participant['gender'] ?? ''));
    $genderCode = '';
    if ($gender === 'Laki-laki') {
        $genderCode = 'L';
    } elseif ($gender === 'Perempuan') {
        $genderCode = 'P';
    }

    $email = strtolower(trim((string) ($participant['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = '';
    }

    return [
        trim((string) ($participant['id'] ?? '')),
        trim((string) ($participant['full_name'] ?? '')),
        trim((string) ($participant['whatsapp'] ?? '')),
        $genderCode,
        normalize_ymd_date((string) ($participant['birth_date'] ?? '')),
        trim((string) ($participant['birth_place'] ?? '')),
        trim((string) ($participant['address'] ?? '')),
        $email,
        import_normalize_month_strict((string) ($participant['msk_month'] ?? '')),
        implode(',', array_map('strval', normalize_msk_session_numbers($participant['session_numbers'] ?? []))),
        trim((string) ($participant['notes'] ?? '')),
    ];
}
