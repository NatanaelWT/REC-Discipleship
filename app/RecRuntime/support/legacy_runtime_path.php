<?php

function legacy_runtime_path(string $path = ''): string {
    $root = defined('REC_LEGACY_RUNTIME_PATH') ? (string) REC_LEGACY_RUNTIME_PATH : __DIR__;
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $path = trim(str_replace('\\', '/', $path), '/');
    return $path === '' ? $root : $root . '/' . $path;
}
