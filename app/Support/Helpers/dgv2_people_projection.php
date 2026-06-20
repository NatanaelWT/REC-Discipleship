<?php

function dgv2_people_projection(array $model, array $members, array $mskClasses): array
{
    $identityById = [];
    foreach (dgv2_identity_sources($members, $mskClasses) as $row) {
        $identityById[(string) ($row['id'] ?? '')] = $row;
    }
    $childrenMap = [];
    $parentsByDisciple = [];
    foreach ($model['discipleship_relations'] as $relation) {
        if (! is_array($relation) || ! dgv2_is_current_period($relation)) {
            continue;
        }
        $mentorId = trim((string) ($relation['mentor_person_id'] ?? ''));
        $discipleId = trim((string) ($relation['disciple_person_id'] ?? ''));
        if ($mentorId !== '') {
            $childrenMap[$mentorId] = true;
        }
        if ($mentorId !== '' && $discipleId !== '') {
            $parentsByDisciple[$discipleId][$mentorId] = true;
        }
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
        $parentIds = array_keys($parentsByDisciple[$personId] ?? []);
        $rows[] = [
            'id' => $personId,
            'member_id' => $memberId,
            'name' => trim((string) ($person['full_name'] ?? $identity['full_name'] ?? '')),
            'phone' => trim((string) ($person['phone'] ?? $identity['whatsapp'] ?? '')),
            'gender' => trim((string) ($person['gender'] ?? $identity['gender'] ?? '')),
            'role' => (isset($leadersMap[$personId]) || isset($childrenMap[$personId])) ? 'Pemimpin' : 'Anggota',
            'parent_ids' => $parentIds,
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
