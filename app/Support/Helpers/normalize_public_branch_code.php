<?php

use App\Services\Branches\BranchCatalog;

function normalize_public_branch_code(string $branch): string
{
    return app(BranchCatalog::class)->normalizeSlug($branch) ?: 'kutisari';
}
