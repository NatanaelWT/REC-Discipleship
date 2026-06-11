<?php

function scoped_data_path(string $name, string $branch): string {
    $branchScopedDataNames = branch_scoped_data_names();
    if (isset($branchScopedDataNames[$name])) {
        return branch_scoped_virtual_data_path($name, normalize_user_branch($branch));
    }
    return legacy_runtime_path('data/' . $name . '.json');
}
