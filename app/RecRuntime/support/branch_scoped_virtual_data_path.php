<?php

function branch_scoped_virtual_data_path(string $name, string $branch): string {
    $name = canonical_data_name($name);
    $safeBranch = strtolower(trim($branch));
    $safeBranch = preg_replace('/[^a-z0-9_-]+/', '', $safeBranch) ?? '';
    if ($safeBranch === '' || !is_known_public_branch_code($safeBranch)) {
        $safeBranch = 'kutisari';
    }
    return legacy_runtime_path('data/' . $name . '.json') . '?branch=' . normalize_public_branch_code($safeBranch);
}
