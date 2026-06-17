<?php

function is_photo_path_used_in_msk_classes(array $mskClasses, string $photoPath, string $excludeParticipantId = ''): bool {
    $safePath = sanitize_relative_upload_path($photoPath);
    if ($safePath === '') {
        return false;
    }
    $excludeParticipantId = trim($excludeParticipantId);
    foreach ($mskClasses as $participant) {
        if (!is_array($participant)) {
            continue;
        }
        $participantId = trim((string) ($participant['id'] ?? ''));
        if ($excludeParticipantId !== '' && $participantId === $excludeParticipantId) {
            continue;
        }
        foreach (extract_msk_participant_photos($participant) as $photo) {
            $participantPhotoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($participantPhotoPath !== '' && $participantPhotoPath === $safePath) {
                return true;
            }
        }
    }
    return false;
}
