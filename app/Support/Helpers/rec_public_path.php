<?php

function rec_public_path(string $path = ''): string {
    $root = defined('REC_PUBLIC_PATH') ? (string) REC_PUBLIC_PATH : rec_runtime_path();
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $path = trim(str_replace('\\', '/', $path), '/');
    return $path === '' ? $root : $root . '/' . $path;
}
