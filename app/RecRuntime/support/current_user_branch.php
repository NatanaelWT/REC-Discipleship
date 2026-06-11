<?php

function current_user_branch(): string {
    $sessionBranch = trim((string) ($_SESSION['cabang'] ?? ''));
    if ($sessionBranch !== '') {
        return normalize_user_branch($sessionBranch);
    }
    // Backward compatibility for old session key before migration to "cabang".
    return normalize_user_branch((string) ($_SESSION['role'] ?? 'kutisari'));
}
