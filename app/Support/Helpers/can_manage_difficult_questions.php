<?php

function can_manage_difficult_questions(): bool {
    if (!is_logged_in()) {
        return false;
    }
    if (function_exists('is_superuser_session') && is_superuser_session()) {
        return true;
    }
    return current_user_branch() === 'pusat' && is_central_discipleship_readonly_session();
}
