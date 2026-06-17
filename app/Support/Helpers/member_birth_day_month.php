<?php

function member_birth_day_month(array $member): string {
    $birthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
    if ($birthDate !== '') {
        $timestamp = strtotime($birthDate);
        if ($timestamp !== false) {
            return date('d-m', $timestamp);
        }
    }
    return normalize_member_birth_day_month_value((string) ($member['birth_day_month'] ?? ''));
}
