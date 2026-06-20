<?php

use App\Services\Branches\BranchCatalog;

function branch_id_from_slug(string $branch): ?int
{
    return app(BranchCatalog::class)->idForSlug($branch);
}
