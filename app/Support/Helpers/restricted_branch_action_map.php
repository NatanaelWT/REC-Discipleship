<?php

function restricted_branch_action_map(): array {
    static $actions = null;
    if ($actions === null) {
        $actions = discipleship_action_map();
        foreach ([
            'logout',
            'change_password',
            'export_pohon_pemuridan_dot',
            'save_discipleship_targets',
            'save_public_dg_report',
            'save_public_member_feedback',
        ] as $action) {
            $actions[$action] = true;
        }
    }
    return $actions;
}
