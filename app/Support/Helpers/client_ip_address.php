<?php

function client_ip_address(): string {
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
        return $ip;
    }
    return 'unknown';
}
