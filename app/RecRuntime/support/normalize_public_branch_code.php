<?php

function normalize_public_branch_code(string $branch): string {
    $branch = strtolower(trim($branch));
    $allowed = ['kutisari', 'gm', 'darmo', 'merr', 'batam', 'nginden'];
    if (!in_array($branch, $allowed, true)) {
        return 'kutisari';
    }
    return $branch;
}
