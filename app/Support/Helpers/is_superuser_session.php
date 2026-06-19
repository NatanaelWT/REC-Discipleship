<?php

function is_superuser_session(): bool {
    return function_exists('is_developer_session') && is_developer_session();
}
