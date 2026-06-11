<?php

function normalize_msk_participant_status(string $value): string {
    $value = strtolower(trim($value));
    if (in_array($value, ['inactive', 'nonaktif', 'nonactive', 'disabled', 'archived'], true)) {
        return 'inactive';
    }
    return 'active';
}
