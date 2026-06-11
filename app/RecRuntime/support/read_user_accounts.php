<?php

function read_user_accounts(): array {
    $users = read_json(data_path('users'), []);
    if (!is_array($users)) {
        $users = [];
    }
    return array_values(array_filter($users, 'is_array'));
}
