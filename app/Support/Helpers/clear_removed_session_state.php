<?php

function clear_removed_session_state(): void {
    unset(
        $_SESSION['preview_origin_user'],
        $_SESSION['preview_origin_cabang'],
        $_SESSION['preview_origin_access_scope'],
        $_SESSION['preview_origin_login_at'],
        $_SESSION['preview_target_user'],
        $_SESSION['preview_started_at']
    );
}
