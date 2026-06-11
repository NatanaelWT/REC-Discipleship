<?php

function restricted_secure_upload_prefixes(): array {
    static $prefixes = null;
    if ($prefixes === null) {
        $prefixes = [
            'uploads/dg_reports/',
            'uploads/jemaat/',
        ];
    }
    return $prefixes;
}
