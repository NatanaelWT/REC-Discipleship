<?php

function is_member_active(array $member): bool {
    return normalize_member_status_value((string) ($member['membership_status'] ?? 'active')) === 'active';
}
