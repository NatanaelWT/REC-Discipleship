<?php

use App\Services\Branches\BranchCatalog;

function central_recap_selected_branch(): string
{
    $includeInactive = function_exists('is_developer_session') && is_developer_session();
    if (! is_effective_central_discipleship_readonly() && ! $includeInactive) {
        return 'all';
    }

    if (request()->query->has('branch_id')) {
        $branchIdInput = trim((string) request()->query('branch_id', 'all'));
        if ($branchIdInput === '' || $branchIdInput === 'all') {
            session()->put('central_rekap_branch_id', 'all');

            return 'all';
        }

        $branchId = filter_var($branchIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($branchId !== false && app(BranchCatalog::class)->isActiveId($branchId, $includeInactive)) {
            session()->put('central_rekap_branch_id', $branchId);

            return app(BranchCatalog::class)->slugForId($branchId);
        }
    }

    $legacyQuery = trim((string) request()->query('rekap_cabang', ''));
    if ($legacyQuery !== '') {
        $selected = normalize_central_recap_branch($legacyQuery);
        $branchId = $selected !== 'all' ? app(BranchCatalog::class)->idForSlug($selected, $includeInactive) : null;
        session()->put('central_rekap_branch_id', $branchId ?? 'all');

        return $selected;
    }

    $fromSession = session('central_rekap_branch_id', 'all');
    if (is_numeric($fromSession)) {
        $branchCode = app(BranchCatalog::class)->slugForId((int) $fromSession);
        if ($branchCode !== '') {
            return $branchCode;
        }
    }

    return 'all';
}
