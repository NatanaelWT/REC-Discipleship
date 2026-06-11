<?php

function completed_msk_person_sources(array $mskClasses): array {
    $sources = [];
    $seenIds = [];
    foreach ($mskClasses as $participant) {
        if (!is_array($participant) || !msk_is_complete($participant)) {
            continue;
        }
        if (normalize_msk_participant_status((string) ($participant['status'] ?? 'active')) !== 'active') {
            continue;
        }
        $participantId = trim((string) ($participant['member_id'] ?? $participant['id'] ?? ''));
        $fullName = trim((string) ($participant['full_name'] ?? ''));
        if ($participantId === '' || $fullName === '' || isset($seenIds[$participantId])) {
            continue;
        }
        $seenIds[$participantId] = true;
        $sources[] = [
            'id' => $participantId,
            'full_name' => $fullName,
            'whatsapp' => trim((string) ($participant['whatsapp'] ?? '')),
        ];
    }
    return $sources;
}
