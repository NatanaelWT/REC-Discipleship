<?php

if ($action === 'change_password') {
    if (!is_logged_in()) {
        redirect_to('login');
    }
    $currentUser = current_username();
    $currentPass = (string) ($_POST['current_password'] ?? '');
    $newPass = (string) ($_POST['new_password'] ?? '');
    $newPassConfirm = (string) ($_POST['new_password_confirm'] ?? '');
    if ($currentPass === '' || $newPass === '' || $newPassConfirm === '') {
        redirect_to('settings', ['error' => 'missing_pw_field']);
    }
    if ($newPass !== $newPassConfirm) {
        redirect_to('settings', ['error' => 'pw_mismatch']);
    }
    if (strlen($newPass) < 6) {
        redirect_to('settings', ['error' => 'pw_short']);
    }
    $accountCheck = find_auth_account($currentUser, $currentPass);
    if ($accountCheck === null) {
        redirect_to('settings', ['error' => 'pw_wrong']);
    }
    if (!update_user_password($currentUser, $newPass)) {
        redirect_to('settings', ['error' => 'pw_save_failed']);
    }
    redirect_to('settings', ['pw_changed' => 1]);
}
