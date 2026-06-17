<?php

function worship_only_action_map(): array {
    static $actions = null;
    if ($actions === null) {
        $actions = worship_action_map();
        foreach ([
            'logout',
            'change_password',
        ] as $action) {
            $actions[$action] = true;
        }
    }
    return $actions;
}
