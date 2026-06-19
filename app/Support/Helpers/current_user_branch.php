<?php

function current_user_branch(): string {
    if (function_exists('is_developer_session') && is_developer_session()) {
        $developerBranch = trim((string) ($_SESSION['developer_branch'] ?? ''));
        if ($developerBranch !== '') {
            return normalize_user_branch($developerBranch);
        }

        return normalize_user_branch('kutisari');
    }

    $sessionBranch = trim((string) ($_SESSION['cabang'] ?? ''));
    if ($sessionBranch !== '') {
        return normalize_user_branch($sessionBranch);
    }
    // Backward compatibility for old session key before migration to "cabang".
    return normalize_user_branch((string) ($_SESSION['role'] ?? 'kutisari'));
}
