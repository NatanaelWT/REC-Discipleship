<?php

function normalize_member_status_value(string $value): string {
    $value = strtolower(trim($value));
    if (in_array($value, ['left', 'keluar', 'sudah_keluar', 'sudah keluar', 'inactive', 'nonactive'], true)) {
        return 'left';
    }
    if (in_array($value, ['active', 'aktif'], true)) {
        return 'active';
    }
    return 'active';
}
