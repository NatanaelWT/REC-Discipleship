<?php

function is_top_level_navigation_request(): bool {
    $dest = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')));
    if ($dest !== '') {
        return $dest === 'document';
    }
    $mode = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '')));
    if ($mode !== '') {
        return $mode === 'navigate';
    }
    return false;
}
