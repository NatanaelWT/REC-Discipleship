<?php

use App\Services\Branches\BranchCatalog;

function normalize_user_branch(string $branch): string
{
    $includeInactive = function_exists('is_developer_session') && is_developer_session();

    return app(BranchCatalog::class)->normalizeSlug($branch, $includeInactive);
}
