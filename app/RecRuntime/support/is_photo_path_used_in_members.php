<?php

function is_photo_path_used_in_members(array $members, string $photoPath, string $excludeMemberId = ''): bool {
    $safePath = sanitize_relative_upload_path($photoPath);
    if ($safePath === '') {
        return false;
    }
    $excludeMemberId = trim($excludeMemberId);
    foreach ($members as $member) {
        if (!is_array($member)) {
            continue;
        }
        $memberId = trim((string) ($member['id'] ?? ''));
        if ($excludeMemberId !== '' && $memberId === $excludeMemberId) {
            continue;
        }
        foreach (extract_member_photos($member) as $photo) {
            $memberPhotoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($memberPhotoPath !== '' && $memberPhotoPath === $safePath) {
                return true;
            }
        }
    }
    return false;
}
