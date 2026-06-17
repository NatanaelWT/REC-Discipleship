<?php

function user_branch_label(string $branch): string {
    $branch = normalize_user_branch($branch);
    if ($branch === 'pusat') {
        return 'Pusat';
    }
    return public_branch_label($branch);
}
