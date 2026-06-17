<?php

function has_member_by_full_name(array $members, string $fullName): bool {
    $nameKey = strtolower(trim($fullName));
    if ($nameKey === '') {
        return false;
    }

    foreach ($members as $member) {
        if (!is_array($member)) {
            continue;
        }
        $memberName = strtolower(trim((string) ($member['full_name'] ?? '')));
        if ($memberName === $nameKey) {
            return true;
        }
    }

    return false;
}
