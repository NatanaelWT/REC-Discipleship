<?php

function data_path(string $name): string {
    $name = canonical_data_name($name);
    $baseDir = legacy_runtime_path('data');
    $branchScopedDataNames = branch_scoped_data_names();
    if (isset($branchScopedDataNames[$name])) {
        $branch = 'kutisari';
        if (function_exists('is_public_dg_flow_request') && is_public_dg_flow_request()) {
            $branch = requested_public_dg_branch();
        } elseif (function_exists('is_logged_in') && is_logged_in() && function_exists('current_user_branch')) {
            $branch = current_user_branch();
        } else {
            $branch = requested_public_dg_branch();
        }
        $safeBranch = strtolower($branch);
        $safeBranch = preg_replace('/[^a-z0-9_-]+/', '', $safeBranch) ?? '';
        if ($safeBranch === '') {
            $safeBranch = 'kutisari';
        }
        return branch_scoped_virtual_data_path($name, $safeBranch);
    }

    return $baseDir . '/' . $name . '.json';
}
