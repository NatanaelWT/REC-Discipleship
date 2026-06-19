<?php

function branch_home_page(string $branch): string {
    if (function_exists('is_developer_session') && is_developer_session()) {
        return 'developer_dashboard';
    }
    if (current_user_can_access_worship()) {
        return 'worship_penatalayan';
    }
    if (is_worship_only_scope(current_auth_access_scope())) {
        return 'settings';
    }
    if (is_central_discipleship_readonly_session() || is_discipleship_branch_scope(current_auth_access_scope())) {
        return 'discipleship_dashboard';
    }
    return 'discipleship_dashboard';
}
