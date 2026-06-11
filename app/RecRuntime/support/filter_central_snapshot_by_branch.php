<?php

function filter_central_snapshot_by_branch(array $snapshot, string $selectedBranch): array {
    $selectedBranch = normalize_central_recap_branch($selectedBranch);
    if ($selectedBranch === 'all') {
        return $snapshot;
    }

    $keys = ['people', 'groups', 'dg_meeting_reports', 'members', 'msk_classes'];
    foreach ($keys as $key) {
        $rows = $snapshot[$key] ?? [];
        if (!is_array($rows)) {
            $snapshot[$key] = [];
            continue;
        }
        $snapshot[$key] = array_values(array_filter($rows, function ($row) use ($selectedBranch) {
            if (!is_array($row)) {
                return false;
            }
            $branchCode = normalize_public_branch_code((string) ($row['branch_code'] ?? ''));
            return $branchCode === $selectedBranch;
        }));
    }
    $modelRows = $snapshot['discipleship_v2_model'] ?? null;
    if (is_array($modelRows)) {
        if (isset($modelRows['groups_v2']) && is_array($modelRows['groups_v2'])) {
            if (!isset($modelRows['discipleship_groups']) || !is_array($modelRows['discipleship_groups'])) {
                $modelRows['discipleship_groups'] = $modelRows['groups_v2'];
            }
            unset($modelRows['groups_v2']);
        }
        foreach (dgv2_model_names() as $modelName) {
            $rows = $modelRows[$modelName] ?? [];
            if (!is_array($rows)) {
                $modelRows[$modelName] = [];
                continue;
            }
            $modelRows[$modelName] = array_values(array_filter($rows, function ($row) use ($selectedBranch) {
                if (!is_array($row)) {
                    return false;
                }
                $branchCode = normalize_public_branch_code((string) ($row['branch_code'] ?? ''));
                return $branchCode === $selectedBranch;
            }));
        }
        $snapshot['discipleship_v2_model'] = $modelRows;
    }
    return $snapshot;
}
