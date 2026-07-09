<?php

use App\Services\Branches\BranchCatalog;

function branch_id_from_slug(string $branch): ?int
{
    $includeDeveloperOnly = function_exists('is_developer_session') && is_developer_session();

    return app(BranchCatalog::class)->idForSlug($branch, $includeDeveloperOnly);
}
