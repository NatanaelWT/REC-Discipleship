<?php

function can_manage_difficult_questions(): bool {
    return is_logged_in() && is_developer_session();
}
