<?php

function unified_move_profile_field_from_payload(array &$profile, array &$payload, string $payloadKey, string $profileKey): void {
    $value = trim((string) ($payload[$payloadKey] ?? ''));
    if ($value === '') {
        unset($payload[$payloadKey]);
        return;
    }
    if ($profileKey === 'gender') {
        $value = normalize_member_gender_value($value);
    }
    if ($value === '') {
        return;
    }

    $profileValue = trim((string) ($profile[$profileKey] ?? ''));
    if ($profileKey === 'gender') {
        $profileValue = normalize_member_gender_value($profileValue);
    }
    if ($profileValue === '') {
        $profile[$profileKey] = $value;
        unset($payload[$payloadKey]);
        return;
    }

    $matches = $profileValue === $value;
    if (!$matches && $profileKey === 'whatsapp') {
        $matches = normalize_whatsapp_digits($profileValue) === normalize_whatsapp_digits($value);
    }
    if ($matches) {
        unset($payload[$payloadKey]);
    }
}
