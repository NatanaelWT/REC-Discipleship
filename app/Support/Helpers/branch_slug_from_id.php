<?php

use App\Services\Branches\BranchCatalog;

function branch_slug_from_id(int|string|null $branchId): string
{
    return app(BranchCatalog::class)->slugForId($branchId);
}
