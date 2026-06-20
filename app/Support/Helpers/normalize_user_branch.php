<?php

use App\Services\Branches\BranchCatalog;

function normalize_user_branch(string $branch): string
{
    return app(BranchCatalog::class)->normalizeSlug($branch);
}
