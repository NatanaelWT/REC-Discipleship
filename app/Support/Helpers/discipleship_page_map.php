<?php

function discipleship_page_map(): array {
    static $pages = null;
    if ($pages === null) {
        $pages = [
            'discipleship_dashboard' => true,
            'groups_list' => true,
            'people_list' => true,
            'people_tree' => true,
            'people_tree_v2' => true,
            'spiritual_journey' => true,
            'dg_reports_recap' => true,
            'msk_classes' => true,
        ];
    }
    return $pages;
}
