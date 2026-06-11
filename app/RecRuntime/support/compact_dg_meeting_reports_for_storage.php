<?php

function compact_dg_meeting_reports_for_storage(array $reports): array {
    $compactReports = [];
    foreach ($reports as $report) {
        if (!is_array($report)) {
            continue;
        }
        $reportId = trim((string) ($report['id'] ?? ''));
        if ($reportId === '') {
            continue;
        }

        $compactRow = $report;
        unset(
            $compactRow['leader_name'],
            $compactRow['group_name'],
            $compactRow['absent_member_names'],
            $compactRow['meditation_sharer_names']
        );
        $compactReports[] = $compactRow;
    }
    return array_values($compactReports);
}
