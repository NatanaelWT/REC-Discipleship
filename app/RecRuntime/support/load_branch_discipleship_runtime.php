<?php

function load_branch_discipleship_runtime(string $branch): array {
    $branchCode = normalize_public_branch_code($branch);
    $memberMskUnifiedRecords = read_json(scoped_data_path(PEOPLE_REGISTRY_DATA_NAME, $branchCode), []);
    if (!is_array($memberMskUnifiedRecords)) {
        $memberMskUnifiedRecords = [];
    }
    $memberMskUnifiedRecords = normalize_people_registry_records($memberMskUnifiedRecords);
    $memberMskViews = people_registry_views($memberMskUnifiedRecords);
    $branchMembers = is_array($memberMskViews['members'] ?? null) ? $memberMskViews['members'] : [];
    $branchMskClasses = is_array($memberMskViews['msk_classes'] ?? null) ? $memberMskViews['msk_classes'] : [];
    $branchReports = read_json(scoped_data_path('dg_meeting_reports', $branchCode), []);
    if (!is_array($branchReports)) {
        $branchReports = [];
    }
    $branchV2Model = dgv2_read_model($branchCode);
    $branchPeople = dgv2_people_projection($branchV2Model, $branchMembers, $branchMskClasses);
    $branchPeopleById = index_by_id($branchPeople);
    $branchGroups = dgv2_groups_projection($branchV2Model, $branchPeopleById);
    $branchGroupsById = index_by_id($branchGroups);
    $branchReports = hydrate_dg_meeting_reports_for_runtime($branchReports, $branchGroupsById, $branchPeopleById);

    return [
        'branch' => $branchCode,
        'members' => $branchMembers,
        'msk_classes' => $branchMskClasses,
        'people' => $branchPeople,
        'people_by_id' => $branchPeopleById,
        'groups' => $branchGroups,
        'groups_by_id' => $branchGroupsById,
        'dg_meeting_reports' => $branchReports,
        'model' => $branchV2Model,
    ];
}
