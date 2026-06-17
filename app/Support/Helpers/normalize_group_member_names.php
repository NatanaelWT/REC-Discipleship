<?php

function normalize_group_member_names($value): array {
    if (!is_array($value)) {
        return [];
    }
    $clean = [];
    foreach ($value as $memberId => $memberName) {
        $memberId = trim((string) $memberId);
        $memberName = trim((string) $memberName);
        if ($memberId === '' || $memberName === '') {
            continue;
        }
        $clean[$memberId] = $memberName;
    }
    return $clean;
}
