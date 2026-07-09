<?php

use App\Services\Branches\BranchCatalog;

function user_branch_label(string $branch): string {
    if (trim($branch) === '') {
        return 'Tanpa cabang';
    }

    $branch = normalize_user_branch($branch);
    if ($branch === '') {
        return 'Tanpa cabang';
    }

    $branchId = branch_id_from_slug($branch);

    return $branchId !== null
        ? app(BranchCatalog::class)->labelForId($branchId)
        : 'Tanpa cabang';
}
