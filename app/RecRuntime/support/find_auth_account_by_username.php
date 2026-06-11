<?php

function find_auth_account_by_username(string $username): ?array {
    $username = trim($username);
    if ($username === '') {
        return null;
    }
    foreach (auth_accounts_config() as $account) {
        if (!is_array($account)) {
            continue;
        }
        $accountUser = trim((string) ($account['username'] ?? ''));
        if ($accountUser === '' || !hash_equals($accountUser, $username)) {
            continue;
        }
        return [
            'username' => $accountUser,
            'cabang' => normalize_user_branch((string) ($account['cabang'] ?? 'kutisari')),
            'access_scope' => normalize_auth_access_scope((string) ($account['access_scope'] ?? 'branch')),
        ];
    }
    return null;
}
