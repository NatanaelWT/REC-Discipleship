<?php

function is_public_dg_flow_request(): bool {
    $page = trim((string) ($_GET['page'] ?? ''));
    $action = trim((string) ($_POST['action'] ?? ''));
    return in_array($page, ['public_dg_report', 'public_dg_branch', 'public_member_feedback', 'public_member_feedback_branch'], true)
        || in_array($action, ['save_public_dg_report', 'save_public_member_feedback'], true);
}
