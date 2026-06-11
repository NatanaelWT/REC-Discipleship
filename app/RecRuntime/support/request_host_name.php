<?php

function request_host_name(): string {
    $hostHeader = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($hostHeader !== '') {
        $parts = explode(':', $hostHeader, 2);
        $host = strtolower(trim((string) ($parts[0] ?? '')));
        if ($host !== '') {
            return $host;
        }
    }
    $serverName = strtolower(trim((string) ($_SERVER['SERVER_NAME'] ?? '')));
    return $serverName;
}
