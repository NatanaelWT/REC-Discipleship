<?php

function legacy_public_path(string $path = ''): string {
    $root = defined('REC_LEGACY_PUBLIC_PATH') ? (string) REC_LEGACY_PUBLIC_PATH : legacy_runtime_path();
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $path = trim(str_replace('\\', '/', $path), '/');
    return $path === '' ? $root : $root . '/' . $path;
}
