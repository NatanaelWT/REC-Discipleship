<?php

function can_manage_difficult_questions(): bool {
    if (!is_logged_in()) {
        return false;
    }
    return current_user_branch() === 'pusat' && is_central_discipleship_readonly_session();
}
