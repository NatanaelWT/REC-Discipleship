<?php

function find_auth_account(string $username, string $password): ?array {
    $username = trim($username);
    foreach (auth_accounts_config() as $account) {
        $accountUser = trim((string) ($account['username'] ?? ''));
        $accountPass = (string) ($account['password'] ?? '');
        if ($accountUser === '') {
            continue;
        }
        if (!hash_equals($accountUser, $username)) {
            continue;
        }
        if (!hash_equals($accountPass, $password)) {
            return null;
        }
        return [
            'username' => $accountUser,
            'cabang' => normalize_user_branch((string) ($account['cabang'] ?? 'kutisari')),
            'access_scope' => normalize_auth_access_scope((string) ($account['access_scope'] ?? 'branch')),
        ];
    }
    return null;
}
