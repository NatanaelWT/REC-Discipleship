<?php

if ($action === 'login') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $now = time();
    $loginAt = now_iso();
    $ip = client_ip_address();
    $attempts = prune_login_attempts(read_login_attempts(), $now);
    $waitSeconds = login_wait_seconds($attempts, $ip, $now);
    if ($waitSeconds > 0) {
        write_json(data_path('login_attempts'), $attempts);
        redirect_to('login', ['error' => 'locked', 'wait' => $waitSeconds]);
    }

    $account = find_auth_account($user, $pass);
    if ($account !== null) {
        clear_login_failures($attempts, $ip);
        write_json(data_path('login_attempts'), $attempts);
        update_user_last_login((string) ($account['username'] ?? $user), $loginAt);
        session_regenerate_id(true);
        clear_removed_session_state();
        $_SESSION['user'] = (string) ($account['username'] ?? $user);
        $_SESSION['cabang'] = normalize_user_branch((string) ($account['cabang'] ?? 'kutisari'));
        $_SESSION['access_scope'] = normalize_auth_access_scope((string) ($account['access_scope'] ?? 'branch'));
        $_SESSION['login_at'] = $loginAt;
        unset($_SESSION['role']);
        $_SESSION['last_active_at'] = $now;
        redirect_to(branch_home_page((string) ($_SESSION['cabang'] ?? 'kutisari')));
    }
    $waitSeconds = register_login_failure($attempts, $ip, $now);
    write_json(data_path('login_attempts'), $attempts);
    if ($waitSeconds > 0) {
        redirect_to('login', ['error' => 'locked', 'wait' => $waitSeconds]);
    }
    redirect_to('login', ['error' => 1]);
}
