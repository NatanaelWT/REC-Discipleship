<?php

function read_login_attempts(): array {
    $data = read_json(data_path('login_attempts'), []);
    return is_array($data) ? $data : [];
}
