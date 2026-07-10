<?php

function public_branch_label(string $branch): string {
    $branch = normalize_public_branch_code($branch);
    foreach (public_dg_branch_options() as $option) {
        if (($option['code'] ?? '') === $branch) {
            return (string) ($option['label'] ?? 'Tanpa cabang');
        }
    }
    return 'Tanpa cabang';
}
