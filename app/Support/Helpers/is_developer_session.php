<?php

function is_developer_session(): bool {
    return is_logged_in() && current_auth_access_scope() === 'developer';
}
