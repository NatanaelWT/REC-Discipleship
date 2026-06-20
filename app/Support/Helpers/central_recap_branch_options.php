<?php

function central_recap_branch_options(): array
{
    $options = [['id' => null, 'code' => 'all', 'label' => 'Semua Cabang']];
    foreach (public_dg_branch_options() as $branchOption) {
        $branchCode = normalize_public_branch_code((string) ($branchOption['code'] ?? 'kutisari'));
        $branchLabel = trim((string) ($branchOption['label'] ?? strtoupper($branchCode)));
        if ($branchLabel === '') {
            $branchLabel = strtoupper($branchCode);
        }
        $options[] = ['id' => (int) ($branchOption['id'] ?? 0), 'code' => $branchCode, 'label' => $branchLabel];
    }

    return $options;
}
