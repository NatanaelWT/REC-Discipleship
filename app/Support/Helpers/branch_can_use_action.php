<?php

function branch_can_use_action(string $branch, string $action): bool {
    $action = trim($action);
    if ($action === '') {
        return true;
    }
    if (function_exists('is_superuser_session') && is_superuser_session()) {
        return true;
    }
    if (is_worship_action($action)) {
        return current_user_can_access_worship();
    }
    if (is_effective_central_discipleship_readonly() && is_discipleship_action($action)) {
        return false;
    }
    if (is_worship_only_scope(current_auth_access_scope())) {
        return isset(worship_only_action_map()[$action]);
    }
    if (is_discipleship_branch_scope(current_auth_access_scope())) {
        return isset(restricted_branch_action_map()[$action]);
    }
    if (is_central_discipleship_readonly_session()) {
        return isset(central_readonly_action_map()[$action]);
    }
    return true;
}
