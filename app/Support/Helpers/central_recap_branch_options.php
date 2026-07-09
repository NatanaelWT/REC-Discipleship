<?php

use App\Services\Branches\BranchCatalog;

function central_recap_branch_options(): array
{
    $options = [['id' => null, 'code' => 'all', 'label' => 'Semua Cabang']];
    $branchOptions = (function_exists('is_developer_session') && is_developer_session())
        ? array_map(static fn (array $option): array => [
            'id' => $option['id'],
            'code' => $option['slug'],
            'label' => $option['label'],
        ], app(BranchCatalog::class)->options(true, true))
        : public_dg_branch_options();

    foreach ($branchOptions as $branchOption) {
        $branchCode = normalize_user_branch((string) ($branchOption['code'] ?? 'kutisari'));
        $branchLabel = trim((string) ($branchOption['label'] ?? strtoupper($branchCode)));
        if ($branchLabel === '') {
            $branchLabel = strtoupper($branchCode);
        }
        $options[] = ['id' => (int) ($branchOption['id'] ?? 0), 'code' => $branchCode, 'label' => $branchLabel];
    }

    return $options;
}
