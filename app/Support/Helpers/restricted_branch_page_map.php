<?php

function restricted_branch_page_map(): array
{
    static $pages = null;
    if ($pages === null) {
        $pages = discipleship_page_map();
        foreach ([
            'discipleship_targets',
            'difficult_questions_admin',
            'public_dg_branch',
            'public_links',
            'secure_file',
            'public_dg_report',
            'public_member_feedback_branch',
            'public_member_feedback',
            'settings',
            'login',
        ] as $page) {
            $pages[$page] = true;
        }
    }

    return $pages;
}
