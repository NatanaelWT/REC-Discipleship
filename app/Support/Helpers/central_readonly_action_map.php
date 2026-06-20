<?php

function central_readonly_action_map(): array {
    static $actions = null;
    if ($actions === null) {
        $actions = [
            'logout' => true,
            'change_password' => true,
            'export_pohon_pemuridan_dot' => true,
        ];
    }
    return $actions;
}
