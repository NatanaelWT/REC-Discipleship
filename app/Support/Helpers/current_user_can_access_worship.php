<?php

function current_user_can_access_worship(): bool {
    return is_logged_in() && username_can_access_worship(current_username());
}
