<?php

function import_normalize_gender_value(string $value): string {
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }
    if (in_array($value, ['l', 'lk', 'laki', 'laki-laki', 'pria', 'male'], true)) {
        return 'Laki-laki';
    }
    if (in_array($value, ['p', 'pr', 'perempuan', 'wanita', 'female'], true)) {
        return 'Perempuan';
    }
    return normalize_member_gender_value($value);
}
