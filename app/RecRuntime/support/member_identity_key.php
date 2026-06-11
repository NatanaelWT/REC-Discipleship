<?php

function member_identity_key(array $member): string {
    $fullNameKey = strtolower(trim((string) ($member['full_name'] ?? '')));
    $whatsappKey = normalize_whatsapp_digits((string) ($member['whatsapp'] ?? ''));
    if ($fullNameKey === '' || $whatsappKey === '') {
        return '';
    }
    return $fullNameKey . '|' . $whatsappKey;
}
