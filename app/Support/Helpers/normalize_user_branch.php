<?php

function normalize_user_branch(string $branch): string {
    $branch = strtolower(trim($branch));
    $allowed = ['kutisari', 'gm', 'darmo', 'merr', 'batam', 'nginden', 'pusat'];
    if (!in_array($branch, $allowed, true)) {
        return 'kutisari';
    }
    return $branch;
}
