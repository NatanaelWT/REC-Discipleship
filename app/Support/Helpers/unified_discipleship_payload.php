<?php

function unified_discipleship_payload(array $source, array $fallback = []): array {
    $personId = unified_pick_string($source, $fallback, ['person_id', 'id'], '');
    if ($personId === '') {
        $personId = generate_id('person');
    }

    $memberId = unified_pick_string($source, $fallback, ['member_id'], '');
    $name = unified_pick_string($source, $fallback, ['name', 'full_name'], '');
    $phone = unified_pick_string($source, $fallback, ['phone', 'whatsapp'], '');
    $role = unified_pick_string($source, $fallback, ['role'], 'Anggota');
    if ($role === '') {
        $role = 'Anggota';
    }

    $parentIdsInput = [];
    if (array_key_exists('parent_ids', $source) && is_array($source['parent_ids'])) {
        $parentIdsInput = $source['parent_ids'];
    } elseif (array_key_exists('parent_id', $source)) {
        $parentIdsInput = [$source['parent_id']];
    } elseif (array_key_exists('parent_ids', $fallback) && is_array($fallback['parent_ids'])) {
        $parentIdsInput = $fallback['parent_ids'];
    } elseif (array_key_exists('parent_id', $fallback)) {
        $parentIdsInput = [$fallback['parent_id']];
    }
    $parentIds = [];
    foreach ($parentIdsInput as $parentIdRaw) {
        $parentId = trim((string) $parentIdRaw);
        if ($parentId === '' || $parentId === $personId) {
            continue;
        }
        $parentIds[] = $parentId;
    }
    $parentIds = array_values(array_unique($parentIds));

    $notes = unified_pick_string($source, $fallback, ['notes'], '');
    $kampus = unified_pick_string($source, $fallback, ['kampus'], '');
    $jurusan = unified_pick_string($source, $fallback, ['jurusan'], '');
    $pekerjaan = unified_pick_string($source, $fallback, ['pekerjaan'], '');
    $angkatan = unified_pick_string($source, $fallback, ['angkatan'], '');

    $createdAt = normalize_iso_datetime_to_jakarta(unified_pick_string($source, $fallback, ['created_at'], ''));
    if ($createdAt === '') {
        $createdAt = now_iso();
    }
    $updatedAt = normalize_iso_datetime_to_jakarta(unified_pick_string($source, $fallback, ['updated_at'], ''));
    if ($updatedAt === '') {
        $updatedAt = $createdAt;
    }

    return [
        'is_person' => true,
        'person_id' => $personId,
        'member_id' => $memberId,
        'name' => $name,
        'phone' => $phone,
        'role' => $role,
        'parent_ids' => $parentIds,
        'notes' => $notes,
        'kampus' => $kampus,
        'jurusan' => $jurusan,
        'pekerjaan' => $pekerjaan,
        'angkatan' => $angkatan,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ];
}
