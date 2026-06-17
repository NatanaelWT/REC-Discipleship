<?php

function dgv2_identity_sources(array $members, array $mskClasses): array {
    $sources = [];
    foreach ($members as $member) {
        if (!is_array($member)) {
            continue;
        }
        $id = trim((string) ($member['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $sources[$id] = [
            'id' => $id,
            'full_name' => trim((string) ($member['full_name'] ?? '')),
            'whatsapp' => trim((string) ($member['whatsapp'] ?? '')),
            'gender' => trim((string) ($member['gender'] ?? '')),
            'completed_msk' => false,
            'member_payload' => $member,
            'msk_payload' => null,
        ];
    }
    foreach ($mskClasses as $participant) {
        if (!is_array($participant)) {
            continue;
        }
        if (normalize_msk_participant_status((string) ($participant['status'] ?? 'active')) !== 'active') {
            continue;
        }
        $memberId = trim((string) ($participant['member_id'] ?? ''));
        if ($memberId === '') {
            continue;
        }
        $completed = function_exists('msk_is_complete') ? msk_is_complete($participant) : false;
        if (!isset($sources[$memberId])) {
            $sources[$memberId] = [
                'id' => $memberId,
                'full_name' => trim((string) ($participant['full_name'] ?? '')),
                'whatsapp' => trim((string) ($participant['whatsapp'] ?? '')),
                'gender' => trim((string) ($participant['gender'] ?? '')),
                'completed_msk' => $completed,
                'member_payload' => null,
                'msk_payload' => $participant,
            ];
            continue;
        }
        if ($sources[$memberId]['full_name'] === '') {
            $sources[$memberId]['full_name'] = trim((string) ($participant['full_name'] ?? ''));
        }
        if ($sources[$memberId]['whatsapp'] === '') {
            $sources[$memberId]['whatsapp'] = trim((string) ($participant['whatsapp'] ?? ''));
        }
        if ($sources[$memberId]['gender'] === '') {
            $sources[$memberId]['gender'] = trim((string) ($participant['gender'] ?? ''));
        }
        $sources[$memberId]['completed_msk'] = !empty($sources[$memberId]['completed_msk']) || $completed;
        $sources[$memberId]['msk_payload'] = $participant;
    }
    return $sources;
}
