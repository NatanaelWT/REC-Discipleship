<?php

function discipleship_person_row_from_unified_record(array $record): ?array {
    $payload = $record['discipleship_person'] ?? null;
    if (!is_array($payload)) {
        return null;
    }
    $personId = trim((string) ($payload['person_id'] ?? $payload['id'] ?? ''));
    if ($personId === '') {
        return null;
    }
    $profile = is_array($record['profile'] ?? null) ? $record['profile'] : [];
    $fullName = trim((string) ($payload['full_name'] ?? $payload['name'] ?? ''));
    if ($fullName === '') {
        $fullName = trim((string) ($profile['full_name'] ?? ''));
    }
    $phone = trim((string) ($payload['phone'] ?? $payload['whatsapp'] ?? ''));
    if ($phone === '') {
        $phone = trim((string) ($profile['whatsapp'] ?? ''));
    }
    $gender = trim((string) ($payload['gender'] ?? ''));
    if ($gender === '') {
        $gender = trim((string) ($profile['gender'] ?? ''));
    }
    return [
        'id' => $personId,
        'member_id' => trim((string) ($payload['member_id'] ?? '')),
        'full_name' => $fullName,
        'phone' => $phone,
        'gender' => $gender,
        'status' => trim((string) ($payload['status'] ?? 'active')) ?: 'active',
        'notes' => trim((string) ($payload['notes'] ?? '')),
        'kampus' => trim((string) ($payload['kampus'] ?? '')),
        'jurusan' => trim((string) ($payload['jurusan'] ?? '')),
        'pekerjaan' => trim((string) ($payload['pekerjaan'] ?? '')),
        'created_at' => trim((string) ($payload['created_at'] ?? $record['created_at'] ?? '')),
        'updated_at' => trim((string) ($payload['updated_at'] ?? $record['updated_at'] ?? '')),
    ];
}
