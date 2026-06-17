<?php

function normalize_central_recap_branch(string $branch): string {
    $branch = strtolower(trim($branch));
    foreach (central_recap_branch_options() as $option) {
        if (($option['code'] ?? '') === $branch) {
            return $branch;
        }
    }
    return 'all';
}
