<?php

function central_recap_branch_label(string $branch): string {
    $branch = normalize_central_recap_branch($branch);
    foreach (central_recap_branch_options() as $option) {
        if (($option['code'] ?? '') === $branch) {
            return (string) ($option['label'] ?? 'Semua Cabang');
        }
    }
    return 'Semua Cabang';
}
