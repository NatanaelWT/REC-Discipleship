<?php

function is_known_public_branch_code(string $branch): bool {
    $branch = strtolower(trim($branch));
    if ($branch === '') {
        return false;
    }
    return normalize_public_branch_code($branch) === $branch;
}
