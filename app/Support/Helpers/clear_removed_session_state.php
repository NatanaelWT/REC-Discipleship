<?php

function clear_removed_session_state(): void
{
    session()->forget([
        'preview_origin_user',
        'preview_origin_cabang',
        'preview_origin_access_scope',
        'preview_origin_login_at',
        'preview_target_user',
        'preview_started_at',
    ]);
}
