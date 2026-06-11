<?php

function secure_upload_prefixes_for_current_scope(): array {
    if (is_worship_only_scope(current_auth_access_scope())) {
        return [];
    }
    if (is_discipleship_branch_scope(current_auth_access_scope()) || is_central_discipleship_readonly_session()) {
        return restricted_secure_upload_prefixes();
    }
    return [];
}
