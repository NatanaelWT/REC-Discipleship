<?php

function branch_scoped_data_names(): array {
    return [
        'dg_meeting_reports' => true,
        'discipleship_targets' => true,
        'discipleship_persons' => true,
        'discipleship_relations' => true,
        DISCIPLESHIP_GROUPS_DATA_NAME => true,
        'groups_v2' => true,
        'group_memberships' => true,
        'group_leaderships' => true,
        'group_multiplications' => true,
        'dg_member_feedback_journals' => true,
    ];
}
