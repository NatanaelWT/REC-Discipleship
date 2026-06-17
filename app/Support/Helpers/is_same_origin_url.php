<?php

function is_same_origin_url(string $url): bool {
    $requestHost = request_host_name();
    if ($requestHost === '') {
        return false;
    }
    $parsed = @parse_url($url);
    if (!is_array($parsed)) {
        return false;
    }
    $scheme = strtolower(trim((string) ($parsed['scheme'] ?? '')));
    if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    $urlHost = strtolower(trim((string) ($parsed['host'] ?? '')));
    if ($urlHost === '') {
        return false;
    }
    return hash_equals($requestHost, $urlHost);
}
