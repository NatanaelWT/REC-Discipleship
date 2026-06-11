<?php

function update_user_password(string $username, string $newPassword): bool {
    if ($username === '') {
        return false;
    }
    $users = read_user_accounts();
    $updated = false;
    foreach ($users as &$user) {
        if (!is_array($user)) {
            continue;
        }
        $userName = trim((string) ($user['username'] ?? ''));
        if ($userName === '') {
            continue;
        }
        if (!hash_equals($userName, $username)) {
            continue;
        }
        $user['password'] = (string) $newPassword;
        $user['updated_at'] = now_iso();
        $updated = true;
        break;
    }
    unset($user);
    if ($updated) {
        write_json(data_path('users'), $users);
    }
    return $updated;
}
