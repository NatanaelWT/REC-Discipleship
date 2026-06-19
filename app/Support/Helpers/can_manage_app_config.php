<?php

function can_manage_app_config(): bool {
    return function_exists('is_superuser_session') && is_superuser_session();
}
