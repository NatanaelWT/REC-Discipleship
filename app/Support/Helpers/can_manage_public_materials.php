<?php

function can_manage_public_materials(): bool {
    if (!is_logged_in()) {
        return false;
    }

    return current_user_branch() === 'pusat';
}
