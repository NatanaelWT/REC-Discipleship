<?php

function can_manage_public_materials(): bool {
    return is_logged_in() && is_developer_session();
}
