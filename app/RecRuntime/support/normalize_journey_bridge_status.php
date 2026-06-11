<?php

function normalize_journey_bridge_status(string $value): string {
    $value = strtolower(trim($value));
    if (in_array($value, ['sudah_rg', 'sudah_kgap', 'ikut_keduanya'], true)) {
        return $value;
    }
    return 'belum';
}
