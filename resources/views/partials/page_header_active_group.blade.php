<?php

function page_header_active_group(string $currentPage): string {
    $groupPages = [
        'pemuridan' => array_merge(array_keys(discipleship_page_map()), ['discipleship_targets', 'difficult_questions_admin']),
        'members' => ['member_dashboard', 'members', 'member_completeness', 'member_families', 'member_birthdays'],
        'worship' => ['worship_penatalayan'],
    ];
    foreach ($groupPages as $group => $pages) {
        if (in_array($currentPage, $pages, true)) {
            return $group;
        }
    }
    if ($currentPage === 'settings') {
        return 'settings';
    }
    return 'dashboard';
}
