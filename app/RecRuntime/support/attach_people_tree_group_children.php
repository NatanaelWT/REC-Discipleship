<?php

function attach_people_tree_group_children(array $branch, array $allBranches): array {
    $branchId = trim((string) ($branch['id'] ?? ''));
    if ($branchId === '') {
        $branch['child_groups'] = [];
        return $branch;
    }

    $resolved = [];
    foreach ($allBranches as $candidateBranch) {
        if (!is_array($candidateBranch)) {
            continue;
        }
        $candidateParentId = trim((string) ($candidateBranch['parent_group_id'] ?? ''));
        if ($candidateParentId !== $branchId) {
            continue;
        }
        $resolved[] = attach_people_tree_group_children($candidateBranch, $allBranches);
    }
    usort($resolved, static function (array $a, array $b): int {
        $aTime = trim((string) ($a['created_at'] ?? ''));
        $bTime = trim((string) ($b['created_at'] ?? ''));
        if ($aTime !== $bTime) {
            return strcmp($aTime, $bTime);
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });
    $branch['child_groups'] = $resolved;
    return $branch;
}
