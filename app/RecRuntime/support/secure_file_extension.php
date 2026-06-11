<?php

function secure_file_extension(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    if ($ext === '' || preg_match('/^[a-z0-9]{2,10}$/', $ext) !== 1) {
        return '';
    }
    return $ext;
}
