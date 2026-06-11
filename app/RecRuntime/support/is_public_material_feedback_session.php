<?php

function is_public_material_feedback_session(string $menu, array $row): bool {
    if (!is_public_material_dg_session_menu($menu)) {
        return false;
    }
    return in_array(public_material_session_number($row), [3, 12], true);
}
