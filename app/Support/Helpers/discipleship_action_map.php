<?php

function discipleship_action_map(): array
{
    static $actions = null;
    if ($actions === null) {
        $actions = [
            'save_person' => true,
            'delete_person' => true,
            'save_group' => true,
            'delete_group' => true,
            'leave_person_group' => true,
            'complete_group' => true,
            'reactivate_group' => true,
            'save_msk_participant' => true,
            'save_msk_sessions' => true,
            'save_journey_bridge_status' => true,
            'delete_msk_participant' => true,
            'reactivate_msk_participant' => true,
            'import_pemuridan_excel' => true,
            'export_pemuridan_excel' => true,
            'export_discipleship_people_excel' => true,
        ];
    }

    return $actions;
}
