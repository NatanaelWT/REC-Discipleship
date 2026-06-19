<?php

function can_manage_users(): bool {
    return function_exists('is_superuser_session') && is_superuser_session();
}
