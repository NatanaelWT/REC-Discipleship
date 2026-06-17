<?php

function branch_can_access_secure_upload_path(string $branch, string $path): bool {
    $allowedPrefixes = secure_upload_prefixes_for_current_scope();
    if (count($allowedPrefixes) > 0) {
        foreach ($allowedPrefixes as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }
    return true;
}
