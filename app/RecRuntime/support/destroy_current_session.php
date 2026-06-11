<?php

function destroy_current_session(): void {
    clear_removed_session_state();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'] ?? '/', $params['domain'] ?? '', !empty($params['secure']), !empty($params['httponly']));
    }
    if (session_id() !== '') {
        session_destroy();
    }
}
