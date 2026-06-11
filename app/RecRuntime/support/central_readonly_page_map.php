<?php

function central_readonly_page_map(): array {
    static $pages = null;
    if ($pages === null) {
        $pages = restricted_branch_page_map();
        unset($pages['public_dg_branch'], $pages['public_dg_report'], $pages['public_member_feedback_branch'], $pages['public_member_feedback']);
        $pages['difficult_questions_admin'] = true;
    }
    return $pages;
}
