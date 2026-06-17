<?php

function dgv2_payload_member_ids(array $payload): array {
    $memberIds = [];
    $input = $payload['member_ids'] ?? [];
    if (is_array($input)) {
        foreach ($input as $value) {
            $memberId = trim((string) $value);
            if ($memberId === '' || in_array($memberId, $memberIds, true)) {
                continue;
            }
            $memberIds[] = $memberId;
        }
    }
    $singleMemberId = trim((string) ($payload['member_id'] ?? ''));
    if ($singleMemberId !== '' && !in_array($singleMemberId, $memberIds, true)) {
        $memberIds[] = $singleMemberId;
    }
    return $memberIds;
}
