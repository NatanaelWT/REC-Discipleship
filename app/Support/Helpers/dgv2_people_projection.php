<?php

function dgv2_people_projection(array $model, array $members, array $mskClasses): array
{
    $identityById = [];
    foreach (dgv2_identity_sources($members, $mskClasses) as $row) {
        $identityById[(string) ($row['id'] ?? '')] = $row;
    }
    $leadersMap = [];
    foreach ($model['group_leaderships'] as $leadership) {
        if (! is_array($leadership) || ! dgv2_is_current_period($leadership)) {
            continue;
        }
        $personId = trim((string) ($leadership['leader_person_id'] ?? ''));
        if ($personId !== '') {
            $leadersMap[$personId] = true;
        }
    }

    $rows = [];
    foreach ($model['discipleship_persons'] as $person) {
        if (! is_array($person)) {
            continue;
        }
        $personId = trim((string) ($person['id'] ?? ''));
        $memberId = trim((string) ($person['member_id'] ?? ''));
        $canonicalMemberId = dgv2_canonical_identity_source_id($memberId, $mskClasses);
        if ($canonicalMemberId !== '') {
            $memberId = $canonicalMemberId;
        }
        if ($personId === '') {
            continue;
        }
        if (strtolower(trim((string) ($person['status'] ?? 'active'))) !== 'active') {
            continue;
        }
        $identity = dgv2_find_identity($identityById, $memberId);
        $name = trim((string) ($person['full_name'] ?? '')) ?: trim((string) ($identity['full_name'] ?? ''));
        $phone = trim((string) ($person['phone'] ?? '')) ?: trim((string) ($identity['whatsapp'] ?? ''));
        $gender = trim((string) ($person['gender'] ?? '')) ?: trim((string) ($identity['gender'] ?? ''));
        $rows[] = [
            'id' => $personId,
            'member_id' => $memberId,
            'name' => $name,
            'phone' => $phone,
            'gender' => $gender,
            'role' => isset($leadersMap[$personId]) ? 'Pemimpin' : 'Anggota',
            'parent_ids' => [],
            'notes' => trim((string) ($person['notes'] ?? '')),
            'kampus' => trim((string) ($person['kampus'] ?? '')),
            'jurusan' => trim((string) ($person['jurusan'] ?? '')),
            'pekerjaan' => trim((string) ($person['pekerjaan'] ?? '')),
            'created_at' => trim((string) ($person['created_at'] ?? now_iso())) ?: now_iso(),
            'updated_at' => trim((string) ($person['updated_at'] ?? now_iso())) ?: now_iso(),
        ];
    }
    usort($rows, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return $rows;
}
