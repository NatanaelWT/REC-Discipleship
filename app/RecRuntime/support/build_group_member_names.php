<?php

function build_group_member_names(array $memberIds, array $peopleById, array $fallback = []): array {
    $fallback = normalize_group_member_names($fallback);
    $map = [];
    foreach ($memberIds as $memberIdRaw) {
        $memberId = trim((string) $memberIdRaw);
        if ($memberId === '') {
            continue;
        }
        $memberName = '';
        if (isset($peopleById[$memberId])) {
            $memberName = trim((string) ($peopleById[$memberId]['name'] ?? ''));
        }
        if ($memberName === '' && isset($fallback[$memberId])) {
            $memberName = trim((string) $fallback[$memberId]);
        }
        if ($memberName !== '') {
            $map[$memberId] = $memberName;
        }
    }
    return $map;
}
