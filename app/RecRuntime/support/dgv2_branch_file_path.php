<?php

function dgv2_branch_file_path(string $name, string $branch): string {
    return branch_scoped_virtual_data_path($name, normalize_public_branch_code($branch));
}
