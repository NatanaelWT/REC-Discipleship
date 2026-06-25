<?php

function central_readonly_action_map(): array
{
    static $actions = null;
    if ($actions === null) {
        $actions = [
            'logout' => true,
            'change_password' => true,
            'export_pohon_pemuridan_dot' => true,
            'export_pemuridan_excel' => true,
            'export_discipleship_people_excel' => true,
        ];
    }

    return $actions;
}
