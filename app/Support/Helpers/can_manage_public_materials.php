<?php

function can_manage_public_materials(): bool {
    if (!is_logged_in()) {
        return false;
    }

    if (function_exists('is_superuser_session') && is_superuser_session()) {
        return true;
    }

    return current_user_branch() === 'pusat';
}
