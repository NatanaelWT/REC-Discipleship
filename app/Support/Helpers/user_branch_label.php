<?php

function user_branch_label(string $branch): string {
    if (trim($branch) === '') {
        return 'Tanpa cabang';
    }

    $branch = normalize_user_branch($branch);
    if ($branch === '') {
        return 'Tanpa cabang';
    }

    return public_branch_label($branch);
}
