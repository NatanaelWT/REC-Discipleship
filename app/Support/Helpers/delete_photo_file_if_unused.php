<?php

function delete_photo_file_if_unused(array $members, array $mskClasses, string $photoPath, string $excludeMemberId = '', string $excludeParticipantId = ''): void {
    $safePath = sanitize_relative_upload_path($photoPath);
    if ($safePath === '') {
        return;
    }
    if (is_photo_path_used_in_msk_classes($mskClasses, $safePath, $excludeParticipantId)) {
        return;
    }
    delete_relative_upload_file($safePath);
}
