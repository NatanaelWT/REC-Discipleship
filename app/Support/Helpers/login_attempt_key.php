<?php

function login_attempt_key(string $ip): string {
    return hash('sha256', $ip);
}
