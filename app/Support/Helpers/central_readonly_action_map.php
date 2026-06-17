<?php

function central_readonly_action_map(): array {
    static $actions = null;
    if ($actions === null) {
        $actions = [
            'logout' => true,
            'export_pohon_pemuridan_dot' => true,
            'save_difficult_question_answer' => true,
        ];
    }
    return $actions;
}
