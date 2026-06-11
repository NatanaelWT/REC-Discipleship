<?php

function is_valid_post_origin(): bool {
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        return is_same_origin_url($origin);
    }
    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer !== '') {
        return is_same_origin_url($referer);
    }
    return true;
}
