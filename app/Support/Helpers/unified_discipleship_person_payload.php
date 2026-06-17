<?php

function unified_discipleship_person_payload(array $source, array $fallback = []): array {
    $personId = unified_pick_string($source, $fallback, ['person_id', 'id'], '');
    if ($personId === '') {
        $personId = generate_id('person');
    }
    $memberId = unified_pick_string($source, $fallback, ['member_id'], '');
    $fullName = unified_pick_string($source, $fallback, ['full_name', 'name'], '');
    $phone = unified_pick_string($source, $fallback, ['phone', 'whatsapp'], '');
    $gender = normalize_member_gender_value(unified_pick_string($source, $fallback, ['gender'], ''));
    $status = strtolower(unified_pick_string($source, $fallback, ['status'], 'active'));
    if ($status === '') {
        $status = 'active';
    }
    $createdAt = normalize_iso_datetime_to_jakarta(unified_pick_string($source, $fallback, ['created_at'], ''));
    if ($createdAt === '') {
        $createdAt = now_iso();
    }
    $updatedAt = normalize_iso_datetime_to_jakarta(unified_pick_string($source, $fallback, ['updated_at'], ''));
    if ($updatedAt === '') {
        $updatedAt = $createdAt;
    }
    $payload = [
        'person_id' => $personId,
        'member_id' => $memberId,
        'full_name' => $fullName,
        'phone' => $phone,
        'gender' => $gender,
        'status' => $status,
        'notes' => unified_pick_string($source, $fallback, ['notes'], ''),
        'kampus' => unified_pick_string($source, $fallback, ['kampus'], ''),
        'jurusan' => unified_pick_string($source, $fallback, ['jurusan'], ''),
        'pekerjaan' => unified_pick_string($source, $fallback, ['pekerjaan'], ''),
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ];
    return $payload;
}
