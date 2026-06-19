<?php

function branch_can_access_page(string $branch, string $page): bool {
    $page = trim($page);
    if (function_exists('is_superuser_session') && is_superuser_session()) {
        return true;
    }
    if (is_worship_page($page)) {
        return current_user_can_access_worship();
    }
    if (is_worship_only_scope(current_auth_access_scope())) {
        return isset(worship_only_page_map()[$page]);
    }
    if (is_discipleship_branch_scope(current_auth_access_scope())) {
        return isset(restricted_branch_page_map()[$page]);
    }
    if (is_central_discipleship_readonly_session()) {
        return isset(central_readonly_page_map()[$page]);
    }
    return true;
}
