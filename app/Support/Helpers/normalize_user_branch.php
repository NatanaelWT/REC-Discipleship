<?php

function normalize_user_branch(string $branch): string {
    $branch = strtolower(trim($branch));
    $allowed = ['kutisari', 'gm', 'darmo', 'merr', 'batam', 'nginden'];
    if (!in_array($branch, $allowed, true)) {
        return '';
    }
    return $branch;
}
