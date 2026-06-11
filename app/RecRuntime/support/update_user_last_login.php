<?php

function update_user_last_login(string $username, string $loginAt = ''): bool {
    $username = trim($username);
    if ($username === '') {
        return false;
    }
    $loginAt = normalize_iso_datetime_to_jakarta($loginAt);
    if ($loginAt === '') {
        $loginAt = now_iso();
    }
    $users = read_user_accounts();
    $updated = false;
    foreach ($users as &$user) {
        if (!is_array($user)) {
            continue;
        }
        $userName = trim((string) ($user['username'] ?? ''));
        if ($userName === '' || !hash_equals($userName, $username)) {
            continue;
        }
        if (normalize_iso_datetime_to_jakarta((string) ($user['last_login_at'] ?? '')) === $loginAt) {
            return true;
        }
        $user['last_login_at'] = $loginAt;
        $updated = true;
        break;
    }
    unset($user);
    if ($updated) {
        write_json(data_path('users'), $users);
    }
    return $updated;
}
