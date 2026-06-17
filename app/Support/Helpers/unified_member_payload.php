<?php

function unified_member_payload(array $source, string $defaultRecordId, array $fallback = []): array {
    $memberId = unified_pick_string($source, $fallback, ['member_id', 'id'], '');
    if ($memberId === '') {
        $memberId = $defaultRecordId;
    }
    if ($memberId === '') {
        $memberId = generate_id('member');
    }

    $socialMedia = normalize_social_link_value(unified_pick_string($source, $fallback, ['social_media', 'social_media_link', 'sosmed'], ''));
    $membershipStatus = normalize_member_status_value(unified_pick_string($source, $fallback, ['membership_status', 'status'], 'active'));
    $leftReason = unified_pick_string($source, $fallback, ['left_reason', 'exit_reason', 'alasan_keluar'], '');

    $createdAt = normalize_iso_datetime_to_jakarta(unified_pick_string($source, $fallback, ['created_at'], ''));
    if ($createdAt === '') {
        $createdAt = now_iso();
    }
    $updatedAt = normalize_iso_datetime_to_jakarta(unified_pick_string($source, $fallback, ['updated_at'], ''));
    if ($updatedAt === '') {
        $updatedAt = $createdAt;
    }

    $leftAt = unified_pick_string($source, $fallback, ['left_at', 'exit_at'], '');
    if ($membershipStatus === 'left' && $leftAt === '') {
        $leftAt = $updatedAt;
    }
    if ($membershipStatus !== 'left') {
        $leftReason = '';
        $leftAt = '';
    }

    $familyIdsInput = $source['family_ids'] ?? ($fallback['family_ids'] ?? []);
    if (!is_array($familyIdsInput)) {
        $familyIdsInput = [];
    }
    $familyIds = [];
    foreach ($familyIdsInput as $familyId) {
        $familyId = trim((string) $familyId);
        if ($familyId === '' || $familyId === $memberId) {
            continue;
        }
        $familyIds[] = $familyId;
    }
    $familyIds = array_values(array_unique($familyIds));

    return [
        'is_member' => true,
        'member_id' => $memberId,
        'social_media' => $socialMedia,
        'membership_status' => $membershipStatus,
        'left_reason' => $leftReason,
        'left_at' => $leftAt,
        'family_ids' => $familyIds,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ];
}
