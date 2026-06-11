<?php

function scoped_virtual_id(string $branch, string $id): string {
    $id = trim($id);
    if ($id === '') {
        return '';
    }
    $branch = normalize_public_branch_code($branch);
    return $branch . '__' . $id;
}
