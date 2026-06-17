<?php

function render_sidebar_navigation(string $currentPage, string $currentBranch, bool $discipleshipOnlyAccess, bool $worshipOnlyAccess, string $activeGroup, bool $hideMemberDataFeatures = false, bool $worshipScopeWithoutFeature = false): void {
    if ($worshipOnlyAccess) {
        render_sidebar_nav_group('Ibadah Umum', 'worship', [
            ['label' => 'Penatalayan', 'page' => 'worship_penatalayan', 'href' => route('worship.penatalayan')],
        ], $currentPage, $activeGroup);
        render_sidebar_nav_link('Setting', route('settings'), $activeGroup === 'settings');
        return;
    }

    if ($worshipScopeWithoutFeature) {
        render_sidebar_nav_link('Setting', route('settings'), $activeGroup === 'settings');
        return;
    }

    $discipleshipNavItems = [
        ['label' => 'Dashboard', 'page' => 'discipleship_dashboard', 'href' => route('discipleship.dashboard')],
        ['label' => 'Kelompok DG', 'page' => 'groups_list', 'href' => route('discipleship.groups')],
        ['label' => 'Anggota DG', 'page' => 'people_list', 'href' => route('discipleship.people-list')],
        ['label' => 'Pohon Pemuridan', 'page' => 'people_tree', 'href' => route('discipleship.tree')],
        ['label' => 'Spiritual Journey', 'page' => 'spiritual_journey', 'href' => route('discipleship.spiritual-journey')],
        ['label' => 'Rekap Laporan DG', 'page' => 'dg_reports_recap', 'href' => route('discipleship.reports-recap')],
        ['label' => 'Kelas MSK', 'page' => 'msk_classes', 'href' => route('discipleship.msk-classes')],
        ['label' => 'Target DG & MSK', 'page' => 'discipleship_targets', 'href' => route('discipleship.targets')],
    ];
    if (can_manage_difficult_questions()) {
        $discipleshipNavItems[] = ['label' => 'Pertanyaan Sulit', 'page' => 'difficult_questions_admin', 'href' => route('discipleship.difficult-questions')];
    }
    render_sidebar_nav_group('Pemuridan', 'pemuridan', $discipleshipNavItems, $currentPage, $activeGroup);

    if ($discipleshipOnlyAccess) {
        render_sidebar_nav_link('Setting', route('settings'), $activeGroup === 'settings');
        return;
    }

    render_sidebar_nav_link('Setting', route('settings'), $activeGroup === 'settings');
}
