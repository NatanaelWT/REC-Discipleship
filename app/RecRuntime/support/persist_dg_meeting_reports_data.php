<?php

function persist_dg_meeting_reports_data(array $reports, ?string $branch = null): void {
    $compactReports = compact_dg_meeting_reports_for_storage($reports);
    if ($branch !== null && $branch !== '') {
        $targetBranch = normalize_public_branch_code($branch);
    } else {
        $targetBranch = 'kutisari';
        if (function_exists('is_public_dg_flow_request') && is_public_dg_flow_request()) {
            $targetBranch = requested_public_dg_branch();
        } elseif (function_exists('is_logged_in') && is_logged_in() && function_exists('current_user_branch')) {
            $targetBranch = current_user_branch();
        } else {
            $targetBranch = requested_public_dg_branch();
        }
        $targetBranch = normalize_public_branch_code($targetBranch);
    }
    write_json(scoped_data_path('dg_meeting_reports', $targetBranch), $compactReports);
}
