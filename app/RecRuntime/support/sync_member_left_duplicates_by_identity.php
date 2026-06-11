<?php

function sync_member_left_duplicates_by_identity(array &$members): bool {
    $leftByIdentity = [];
    foreach ($members as $member) {
        if (!is_array($member) || is_member_active($member)) {
            continue;
        }

        $identityKey = member_identity_key($member);
        if ($identityKey === '') {
            continue;
        }

        $leftAt = trim((string) ($member['left_at'] ?? ''));
        $leftAtTimestamp = $leftAt !== '' ? strtotime($leftAt) : false;
        if (!isset($leftByIdentity[$identityKey])) {
            $leftByIdentity[$identityKey] = [
                'left_reason' => trim((string) ($member['left_reason'] ?? '')),
                'left_at' => $leftAt,
                'left_ts' => $leftAtTimestamp !== false ? $leftAtTimestamp : 0,
            ];
            continue;
        }

        if ($leftAtTimestamp !== false && $leftAtTimestamp > $leftByIdentity[$identityKey]['left_ts']) {
            $leftByIdentity[$identityKey] = [
                'left_reason' => trim((string) ($member['left_reason'] ?? '')),
                'left_at' => $leftAt,
                'left_ts' => $leftAtTimestamp,
            ];
        }
    }

    if (count($leftByIdentity) === 0) {
        return false;
    }

    $changed = false;
    foreach ($members as &$member) {
        if (!is_array($member) || !is_member_active($member)) {
            continue;
        }

        $identityKey = member_identity_key($member);
        if ($identityKey === '' || !isset($leftByIdentity[$identityKey])) {
            continue;
        }

        $leftReason = trim((string) ($member['left_reason'] ?? ''));
        if ($leftReason === '') {
            $leftReason = trim((string) ($leftByIdentity[$identityKey]['left_reason'] ?? ''));
            if ($leftReason === '') {
                $leftReason = 'Mengikuti status jemaat keluar dengan identitas yang sama.';
            }
        }

        $leftAt = trim((string) ($leftByIdentity[$identityKey]['left_at'] ?? ''));
        if ($leftAt === '') {
            $leftAt = now_iso();
        }

        $member['membership_status'] = 'left';
        $member['left_reason'] = $leftReason;
        $member['left_at'] = $leftAt;
        $member['updated_at'] = now_iso();
        $changed = true;
    }
    unset($member);

    return $changed;
}
