<?php

function clear_login_failures(array &$attempts, string $ip): void {
    $key = login_attempt_key($ip);
    if (isset($attempts[$key])) {
        unset($attempts[$key]);
    }
}
