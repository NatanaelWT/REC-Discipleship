<?php

function is_member_registered_in_msk(array $mskClasses, string $memberId, string $excludeParticipantId = ''): bool {
    $memberId = trim($memberId);
    if ($memberId === '') {
        return false;
    }
    $excludeParticipantId = trim($excludeParticipantId);
    foreach ($mskClasses as $participant) {
        $participantId = trim((string) ($participant['id'] ?? ''));
        if ($excludeParticipantId !== '' && $participantId === $excludeParticipantId) {
            continue;
        }
        if (normalize_msk_participant_status((string) ($participant['status'] ?? 'active')) !== 'active') {
            continue;
        }
        $linkedMemberId = trim((string) ($participant['member_id'] ?? ''));
        if ($linkedMemberId !== '' && $linkedMemberId === $memberId) {
            return true;
        }
    }
    return false;
}
