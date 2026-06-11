<?php

function worship_action_map(): array {
    static $actions = null;
    if ($actions === null) {
        $actions = [
            'save_worship_penatalayan' => true,
            'delete_worship_penatalayan' => true,
        ];
    }
    return $actions;
}
