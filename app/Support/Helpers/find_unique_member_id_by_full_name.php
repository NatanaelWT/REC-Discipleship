<?php

function find_unique_member_id_by_full_name(array $members, string $fullName): string {
    $nameKey = strtolower(trim($fullName));
    if ($nameKey === '') {
        return '';
    }
    $foundId = '';
    foreach ($members as $member) {
        if (!is_array($member) || !is_member_active($member)) {
            continue;
        }
        $memberId = trim((string) ($member['id'] ?? ''));
        if ($memberId === '') {
            continue;
        }
        $memberName = strtolower(trim((string) ($member['full_name'] ?? '')));
        if ($memberName !== $nameKey) {
            continue;
        }
        if ($foundId !== '' && $foundId !== $memberId) {
            return '';
        }
        $foundId = $memberId;
    }
    return $foundId;
}
