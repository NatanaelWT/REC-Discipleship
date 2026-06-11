<?php

function render_sidebar_navigation(string $currentPage, string $currentBranch, bool $discipleshipOnlyAccess, bool $worshipOnlyAccess, string $activeGroup, bool $hideMemberDataFeatures = false, bool $worshipScopeWithoutFeature = false): void {
    if ($worshipOnlyAccess) {
        render_sidebar_nav_group('Ibadah Umum', 'worship', [
            ['label' => 'Penatalayan', 'page' => 'worship_penatalayan', 'href' => '?page=worship_penatalayan'],
        ], $currentPage, $activeGroup);
        render_sidebar_nav_link('Setting', '?page=settings', $activeGroup === 'settings');
        return;
    }

    if ($worshipScopeWithoutFeature) {
        render_sidebar_nav_link('Setting', '?page=settings', $activeGroup === 'settings');
        return;
    }

    if (!$discipleshipOnlyAccess) {
        if (!$hideMemberDataFeatures) {
            render_sidebar_nav_link('Dashboard', '?page=dashboard', $activeGroup === 'dashboard');
        }
        if (!$hideMemberDataFeatures) {
            render_sidebar_nav_group('Data Jemaat', 'members', [
                ['label' => 'Dashboard', 'page' => 'member_dashboard', 'href' => '?page=member_dashboard'],
                ['label' => 'Pendataan Jemaat', 'page' => 'members', 'href' => '?page=members'],
                ['label' => 'Kelengkapan Data', 'page' => 'member_completeness', 'href' => '?page=member_completeness'],
                ['label' => 'List Keluarga', 'page' => 'member_families', 'href' => '?page=member_families'],
                ['label' => 'Ulang Tahun Bulanan', 'page' => 'member_birthdays', 'href' => '?page=member_birthdays'],
            ], $currentPage, $activeGroup);
        }
    }

    $discipleshipNavItems = [
        ['label' => 'Dashboard', 'page' => 'discipleship_dashboard', 'href' => '?page=discipleship_dashboard'],
        ['label' => 'Kelompok DG', 'page' => 'groups_list', 'href' => '?page=groups_list'],
        ['label' => 'Anggota DG', 'page' => 'people_list', 'href' => '?page=people_list'],
        ['label' => 'Pohon Pemuridan', 'page' => 'people_tree', 'href' => '?page=people_tree'],
        ['label' => 'Spiritual Journey', 'page' => 'spiritual_journey', 'href' => '?page=spiritual_journey'],
        ['label' => 'Rekap Laporan DG', 'page' => 'dg_reports_recap', 'href' => '?page=dg_reports_recap'],
        ['label' => 'Kelas MSK', 'page' => 'msk_classes', 'href' => '?page=msk_classes'],
        ['label' => 'Target DG & MSK', 'page' => 'discipleship_targets', 'href' => '?page=discipleship_targets'],
    ];
    if (can_manage_difficult_questions()) {
        $discipleshipNavItems[] = ['label' => 'Pertanyaan Sulit', 'page' => 'difficult_questions_admin', 'href' => '?page=difficult_questions_admin'];
    }
    render_sidebar_nav_group('Pemuridan', 'pemuridan', $discipleshipNavItems, $currentPage, $activeGroup);

    if ($discipleshipOnlyAccess) {
        render_sidebar_nav_link('Setting', '?page=settings', $activeGroup === 'settings');
        return;
    }

    render_sidebar_nav_link('Setting', '?page=settings', $activeGroup === 'settings');
}
