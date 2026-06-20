<?php

use App\Services\Branches\BranchCatalog;

function public_dg_branch_options(): array
{
    return array_map(
        static fn (array $option): array => [
            'id' => $option['id'],
            'code' => $option['slug'],
            'label' => $option['label'],
        ],
        app(BranchCatalog::class)->options(),
    );
}
