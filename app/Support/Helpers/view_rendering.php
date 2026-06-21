<?php

use App\Services\Routing\AppPageRouteMap;

function append_body_classes(array &$bodyClasses, string $classes): void
{
    if ($classes === '') {
        return;
    }
    $extraClasses = preg_split('/\s+/', trim($classes));
    if (! is_array($extraClasses)) {
        return;
    }
    foreach ($extraClasses as $extraClass) {
        $extraClass = trim((string) $extraClass);
        if ($extraClass === '') {
            continue;
        }
        $bodyClasses[] = $extraClass;
    }
}

function body_class_attr(array $bodyClasses): string
{
    return ' class="'.h(implode(' ', array_values(array_unique($bodyClasses)))).'"';
}

function icon_svg(string $name): string
{
    if ($name === 'edit') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    if ($name === 'plus') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    if ($name === 'expand') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 4H4v4M16 4h4v4M20 16v4h-4M4 16v4h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    if ($name === 'compress') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 4H4v5M15 4h5v5M20 15v5h-5M4 15v5h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 9L4 4M15 9l5-5M15 15l5 5M9 15l-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    if ($name === 'print') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 9V4h12v5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><rect x="4" y="9" width="16" height="8" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 17h10v3H7z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="17" cy="12.5" r="0.8" fill="currentColor"/></svg>';
    }
    if ($name === 'download') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4v10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 10.5l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    if ($name === 'upload') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20V10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 13.5l4-4 4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    if ($name === 'move') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M15 8l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 8l-4 4 4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    if ($name === 'more') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="6" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="18" cy="12" r="1.8" fill="currentColor"/></svg>';
    }
    if ($name === 'check') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 12.5l2.5 2.5L16 9.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    if ($name === 'exit') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 8l5 4-5 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 12H9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    if ($name === 'eye') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M1.5 12s3.8-6 10.5-6 10.5 6 10.5 6-3.8 6-10.5 6S1.5 12 1.5 12z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
    }
    if ($name === 'trash') {
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 6V4h8v2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M6 6l1.2 13a2 2 0 0 0 2 1.8h5.6a2 2 0 0 0 2-1.8L18 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    return '';
}

function page_header_active_group(string $currentPage): string
{
    $groupPages = [
        'developer' => ['developer_dashboard', 'developer_users', 'developer_config', 'developer_statistics', 'developer_activities'],
        'pemuridan' => array_merge(array_keys(discipleship_page_map()), ['discipleship_targets', 'difficult_questions_admin']),
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

    return '';
}

function render_app_document_head(string $app): void
{
    echo "<!doctype html>\n";
    echo "<html lang=\"id\">\n";
    echo "<head>\n";
    echo "  <meta charset=\"utf-8\">\n";
    echo "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo '  <title>'.$app."</title>\n";
    $logoVersion = asset_version('assets/logo.png');
    echo '  <link rel="icon" type="image/png" href="/assets/logo.png'.$logoVersion."\">\n";
    echo '  <link rel="shortcut icon" type="image/png" href="/assets/logo.png'.$logoVersion."\">\n";
    echo "  <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
    echo "  <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
    echo "  <link href=\"https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700&display=swap\" rel=\"stylesheet\">\n";
    $cssVersion = asset_version('assets/style.css');
    echo '  <link rel="stylesheet" href="/assets/style.css'.$cssVersion."\">\n";
    echo "</head>\n";
}

function render_alert(string $tone, string $message): void
{
    echo '<div class="alert '.h($tone).'">'.h($message)."</div>\n";
}

function render_condition_alerts(array $alerts): void
{
    foreach ($alerts as $alert) {
        if (empty($alert['when'])) {
            continue;
        }
        $message = trim((string) ($alert['message'] ?? ''));
        if ($message === '') {
            continue;
        }
        $tone = trim((string) ($alert['tone'] ?? 'success'));
        render_alert($tone !== '' ? $tone : 'success', $message);
    }
}

function render_mapped_error_alert(string $errorCode, array $errorMessages, string $tone = 'danger'): bool
{
    if ($errorCode === '' || ! isset($errorMessages[$errorCode])) {
        return false;
    }
    render_alert($tone, (string) $errorMessages[$errorCode]);

    return true;
}

function render_pemuridan_import_feedback(): void
{
    if (isset($_GET['imported'])) {
        $importMskInserted = max(0, (int) ($_GET['import_msk_inserted'] ?? 0));
        $importMskUpdated = max(0, (int) ($_GET['import_msk_updated'] ?? 0));
        $importErrorCount = max(0, (int) ($_GET['import_error_count'] ?? 0));
        $importSummary = 'Import selesai. Kelas MSK: '.$importMskInserted.' tambah, '.$importMskUpdated.' update.';
        if ($importErrorCount > 0) {
            $importSummary .= ' Ada '.$importErrorCount.' baris gagal.';
        }
        render_alert('success', $importSummary);
        $importPreview = trim((string) ($_GET['import_error_preview'] ?? ''));
        if ($importErrorCount > 0 && $importPreview !== '') {
            render_alert('danger', 'Contoh error: '.$importPreview);
        }
    }

    $error = trim((string) ($_GET['error'] ?? ''));
    $errorMessages = [
        'import_missing_file' => 'Pilih file Excel (.xlsx) untuk import pemuridan.',
        'import_upload_failed' => 'Upload file import gagal. Coba ulangi lagi.',
        'import_invalid_file_type' => 'Format file tidak didukung. Gunakan file Excel (.xlsx).',
        'import_file_too_large' => 'Ukuran file terlalu besar. Maksimal 10 MB.',
        'import_zip_unavailable' => 'Fitur import Excel belum tersedia di server (ekstensi ZipArchive belum aktif).',
        'import_invalid_excel' => 'File Excel tidak valid atau rusak.',
        'import_missing_sheet' => 'Sheet wajib tidak ditemukan. Pastikan ada sheet "Kelas MSK".',
        'import_empty_sheet' => 'Sheet Kelas MSK kosong. Isi minimal 1 baris data.',
    ];
    if (isset($errorMessages[$error])) {
        render_alert('danger', $errorMessages[$error]);
    }
}

function render_central_rekap_toolbar(string $currentPage): void
{
    if (! is_effective_central_discipleship_readonly()
        || page_header_active_group($currentPage) !== 'pemuridan') {
        return;
    }
    $selectedBranch = central_recap_selected_branch();
    $selectedBranchLabel = central_recap_branch_label($selectedBranch);
    $preservedQueryParams = [];
    foreach ($_GET as $paramKey => $paramValue) {
        if (! is_string($paramKey)) {
            continue;
        }
        if (in_array($paramKey, ['page', 'branch_id', 'rekap_cabang'], true)) {
            continue;
        }
        $preservedQueryParams[$paramKey] = $paramValue;
    }
    $buildRekapHref = function (int|string|null $branchId) use ($currentPage, $preservedQueryParams): string {
        $params = array_merge([
            'branch_id' => $branchId ?? 'all',
        ], $preservedQueryParams);

        if (class_exists(AppPageRouteMap::class) && AppPageRouteMap::hasPage($currentPage)) {
            return AppPageRouteMap::pageUrl($currentPage, $params);
        }

        return '?'.http_build_query($params);
    };

    echo "  <section class=\"central-rekap-toolbar\" aria-label=\"Filter rekap Pusat Pemuridan\">\n";
    echo "    <div class=\"central-rekap-toolbar-body\">\n";
    echo "      <div class=\"central-rekap-head\">\n";
    echo "        <div class=\"central-rekap-title-row\">\n";
    echo "          <span class=\"badge warning\">Mode Pusat</span>\n";
    echo '          <span class="central-rekap-current">Rekap aktif: <strong>'.h($selectedBranchLabel)."</strong>. Klik nama cabang di bawah untuk ganti tampilan.</span>\n";
    echo "        </div>\n";
    echo "      </div>\n";
    echo "      <div class=\"central-rekap-quick\" aria-label=\"Pilih cabang rekap Pusat Pemuridan\">\n";
    foreach (central_recap_branch_options() as $branchOption) {
        $branchCode = normalize_central_recap_branch((string) ($branchOption['code'] ?? 'all'));
        $branchId = $branchOption['id'] ?? null;
        $branchLabel = trim((string) ($branchOption['label'] ?? $branchCode));
        if ($branchLabel === '') {
            $branchLabel = $branchCode;
        }
        $chipClass = $branchCode === $selectedBranch ? 'central-rekap-chip active' : 'central-rekap-chip';
        echo '        <a class="'.h($chipClass).'" href="'.h($buildRekapHref($branchId)).'">'.h($branchLabel)."</a>\n";
    }
    echo "      </div>\n";
    echo "    </div>\n";
    echo "  </section>\n";
}

function render_app_script_tag(): void
{
    $jsVersion = asset_version('assets/app.js');
    echo '<script src="/assets/app.js'.$jsVersion."\"></script>\n";
}

function render_sidebar_nav_link(string $label, string $href, bool $active, string $indent = '        ', string $icon = ''): void
{
    $class = $active ? 'nav-item active' : 'nav-item';
    $href = render_sidebar_nav_href($href);
    if ($icon !== '') {
        $class .= ' nav-item-visual';
        echo $indent.'<a class="'.h($class).'" href="'.h($href).'" data-nav-icon="'.h($icon).'">'
            .'<span class="nav-item-visual-icon" aria-hidden="true"></span><span>'.h($label)."</span></a>\n";

        return;
    }

    echo $indent.'<a class="'.h($class).'" href="'.h($href).'">'.h($label)."</a>\n";
}

function render_sidebar_nav_href(string $href): string
{
    $href = trim($href);
    if ($href === '' || ! str_starts_with($href, '?')) {
        return $href;
    }

    parse_str(ltrim($href, '?'), $params);
    $page = trim((string) ($params['page'] ?? ''));
    if ($page === '' || ! class_exists(AppPageRouteMap::class)) {
        return $href;
    }

    if (! AppPageRouteMap::hasPage($page)) {
        return $href;
    }

    return AppPageRouteMap::pageUrl($page, $params);
}

function render_sidebar_nav_group(string $label, string $groupKey, array $items, string $currentPage, string $activeGroup): void
{
    $isOpen = $activeGroup === $groupKey;
    $summaryClass = $isOpen ? 'nav-item active has-sub' : 'nav-item has-sub';
    echo '        <details class="nav-group"'.($isOpen ? ' open' : '').">\n";
    echo '          <summary class="'.h($summaryClass).'">'.h($label)."<span class=\"chevron\">&#9662;</span></summary>\n";
    echo "          <div class=\"nav-sub\">\n";
    foreach ($items as $item) {
        $page = trim((string) ($item['page'] ?? ''));
        $href = trim((string) ($item['href'] ?? ''));
        $extraActivePages = $item['active_pages'] ?? [];
        if (! is_array($extraActivePages)) {
            $extraActivePages = [];
        }
        $isActive = $page !== '' && ($page === $currentPage || in_array($currentPage, $extraActivePages, true));
        render_sidebar_nav_link((string) ($item['label'] ?? ''), $href, $isActive, '            ', (string) ($item['icon'] ?? ''));
    }
    echo "          </div>\n";
    echo "        </details>\n";
}

function render_sidebar_navigation(string $currentPage, string $currentBranch, bool $discipleshipOnlyAccess, bool $worshipOnlyAccess, string $activeGroup, bool $hideMemberDataFeatures = false, bool $worshipScopeWithoutFeature = false): void
{
    $developerAccess = function_exists('is_developer_session') && is_developer_session();
    if ($developerAccess) {
        render_sidebar_nav_group('Developer', 'developer', [
            ['label' => 'Dashboard', 'page' => 'developer_dashboard', 'href' => route('developer.dashboard'), 'icon' => 'dashboard'],
            ['label' => 'User', 'page' => 'developer_users', 'href' => route('developer.users'), 'icon' => 'users'],
            ['label' => 'Config', 'page' => 'developer_config', 'href' => route('developer.config'), 'icon' => 'config'],
            ['label' => 'Statistik', 'page' => 'developer_statistics', 'href' => route('developer.statistics'), 'icon' => 'statistics'],
            ['label' => 'Aktivitas', 'page' => 'developer_activities', 'href' => route('developer.activities'), 'icon' => 'activities'],
        ], $currentPage, $activeGroup);

        render_sidebar_nav_group('Ibadah Umum', 'worship', [
            ['label' => 'Penatalayan', 'page' => 'worship_penatalayan', 'href' => route('worship.penatalayan')],
        ], $currentPage, $activeGroup);
    }

    if (! $developerAccess && $worshipOnlyAccess) {
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

function render_table_search_input(
    string $filterId,
    string $placeholder,
    string $class = 'search',
    string $ariaLabel = '',
    string $indent = ''
): void {
    $attrs = 'class="'.h($class !== '' ? $class : 'search').'" type="search" placeholder="'.h($placeholder).'"';
    if ($ariaLabel !== '') {
        $attrs .= ' aria-label="'.h($ariaLabel).'"';
    }
    $attrs .= ' data-filter="'.h($filterId).'"';
    echo $indent.'<input '.$attrs.">\n";
}

function render_people_tree_v3_group_branch(
    array $groupBranch,
    array $peopleById,
    array $childrenMap,
    array $groupsByLeader,
    array $membersById,
    string $rootLeaderId,
    array $stack,
    int $depth,
    bool $canManageTree,
    string $leaderPersonId,
    string $leaderName
): void {
    $groupName = trim((string) ($groupBranch['name'] ?? 'Kelompok'));
    if ($groupName === '') {
        $groupName = 'Kelompok';
    }
    $groupProgress = trim((string) ($groupBranch['progress'] ?? '-'));
    if ($groupProgress === '') {
        $groupProgress = '-';
    }
    $groupId = trim((string) ($groupBranch['id'] ?? ''));
    $groupAssistantName = trim((string) ($groupBranch['assistant_name'] ?? ''));
    $groupAssistantId = trim((string) ($groupBranch['assistant_id'] ?? ''));
    $groupNotes = str_replace(["\r", "\n"], ' ', (string) ($groupBranch['notes'] ?? ''));
    $groupParentId = trim((string) ($groupBranch['parent_group_id'] ?? ''));
    $groupMemberIds = $groupBranch['member_ids'] ?? [];
    if (! is_array($groupMemberIds)) {
        $groupMemberIds = [];
    }
    $groupChildBranches = $groupBranch['child_groups'] ?? [];
    if (! is_array($groupChildBranches)) {
        $groupChildBranches = [];
    }
    $isUngrouped = ! empty($groupBranch['is_ungrouped']);
    $isVirtualGroup = ! empty($groupBranch['is_virtual']);
    $groupStatus = strtolower(trim((string) ($groupBranch['status'] ?? 'active')));
    $groupItemClass = 'tree-v2-item tree-v2-item-group'.($isUngrouped ? ' is-ungrouped' : '');
    $groupProgressBadgeClass = $groupProgress !== '-' ? 'badge warning' : 'badge muted';
    $groupStatusLabel = $groupStatus === 'active' ? 'Aktif' : 'Selesai';
    $groupStatusBadgeClass = $groupStatus === 'active' ? 'badge tree-v2-status-badge is-active' : 'badge tree-v2-status-badge is-inactive';
    $groupMetaParts = [(string) count($groupMemberIds).' anggota'];
    if ($groupProgress !== '-') {
        $groupMetaParts[] = $groupProgress;
    }
    if ($groupAssistantName !== '') {
        $groupMetaParts[] = 'Pendamping: '.$groupAssistantName;
    }
    if ($groupStatus !== 'active') {
        $groupMetaParts[] = 'Selesai';
    }
    $groupMetaLabel = implode(' • ', $groupMetaParts);

    $groupNodeClass = 'tree-v2-node tree-v2-group';
    if ($groupProgress !== '-') {
        $groupProgressToken = strtolower(str_replace([' ', '-'], '', $groupProgress));
        if ($groupProgressToken === 'dg1') {
            $groupNodeClass .= ' is-dg1';
        } elseif ($groupProgressToken === 'dg2') {
            $groupNodeClass .= ' is-dg2';
        } elseif ($groupProgressToken === 'dg3') {
            $groupNodeClass .= ' is-dg3';
        }
    }
    $canGroupAction = $canManageTree && ! $isUngrouped && ! $isVirtualGroup && $groupId !== '';
    $canGroupViewHistory = ! $isUngrouped && $groupId !== '';
    if ($canGroupAction || $canGroupViewHistory) {
        $groupNodeClass .= ' is-actionable';
    }
    $groupMembersCsv = implode(',', array_map('strval', $groupMemberIds));
    $groupNodeAttrs = '';
    if ($canGroupAction) {
        $groupNodeAttrs .= ' data-tree-v2-node-action="group"';
        $groupNodeAttrs .= ' data-group-id="'.h($groupId).'"';
        $groupNodeAttrs .= ' data-name="'.h($groupName).'"';
        $groupNodeAttrs .= ' data-leader-id="'.h($leaderPersonId).'"';
        $groupNodeAttrs .= ' data-leader-name="'.h($leaderName).'"';
        $groupNodeAttrs .= ' data-assistant-id="'.h($groupAssistantId).'"';
        $groupNodeAttrs .= ' data-progress="'.h($groupProgress).'"';
        $groupNodeAttrs .= ' data-status="'.h($groupStatus).'"';
        $groupNodeAttrs .= ' data-notes="'.h($groupNotes).'"';
        $groupNodeAttrs .= ' data-parent-group-id="'.h($groupParentId).'"';
        $groupNodeAttrs .= ' data-members="'.h($groupMembersCsv).'"';
        $groupNodeAttrs .= ' data-is-virtual="'.($isVirtualGroup ? '1' : '0').'"';
        $groupNodeAttrs .= ' data-is-ungrouped="'.($isUngrouped ? '1' : '0').'"';
        $groupNodeAttrs .= ' tabindex="0" role="button" aria-label="Aksi untuk '.h($groupName).'"';
    } elseif ($canGroupViewHistory) {
        $groupNodeAttrs .= ' data-tree-v2-history-open="'.h($groupId).'"';
        $groupNodeAttrs .= ' tabindex="0" role="button" aria-label="Lihat riwayat kelompok '.h($groupName).'"';
    }

    echo '    <li class="'.h($groupItemClass)."\">\n";
    echo '      <article class="'.h($groupNodeClass).'"'.$groupNodeAttrs.">\n";
    echo "        <div class=\"tree-v2-node-head tree-v2-node-head-groups-only\">\n";
    echo '          <div class="tree-v2-node-badges"><span class="'.h($groupProgressBadgeClass).'">'.h($groupProgress).'</span><span class="'.h($groupStatusBadgeClass).'">'.h($groupStatusLabel)."</span></div>\n";
    echo "        </div>\n";
    echo '        <div class="tree-v2-meta">'.h($groupMetaLabel)."</div>\n";
    echo "      </article>\n";

    if (count($groupMemberIds) > 0 || count($groupChildBranches) > 0) {
        echo "      <ul class=\"tree-v2-children tree-v2-level-members\">\n";
        foreach ($groupChildBranches as $childGroupBranch) {
            if (! is_array($childGroupBranch)) {
                continue;
            }
            render_people_tree_v3_group_branch($childGroupBranch, $peopleById, $childrenMap, $groupsByLeader, $membersById, $rootLeaderId, $stack, $depth + 1, $canManageTree, $leaderPersonId, $leaderName);
        }
        foreach ($groupMemberIds as $memberId) {
            render_people_tree_v3($memberId, $peopleById, $childrenMap, $groupsByLeader, $membersById, $rootLeaderId, $stack, $depth + 1, $canManageTree);
        }
        echo "      </ul>\n";
    } else {
        echo "      <div class=\"tree-v2-empty\">Belum ada anggota</div>\n";
    }
    echo "    </li>\n";
}

function render_people_tree_v3(
    string $personId,
    array $peopleById,
    array $childrenMap,
    array $groupsByLeader,
    array $membersById,
    string $rootLeaderId,
    array $stack = [],
    int $depth = 0,
    bool $canManageTree = true
): void {
    if ($personId === '' || ! isset($peopleById[$personId]) || in_array($personId, $stack, true)) {
        return;
    }

    $stack[] = $personId;
    $person = $peopleById[$personId];
    $isRoot = $personId === $rootLeaderId;
    $personName = trim((string) ($person['name'] ?? '-'));
    if ($personName === '') {
        $personName = '-';
    }
    $role = trim((string) ($person['role'] ?? ''));
    if ($role === '') {
        $role = $isRoot ? 'Leader Utama' : 'Anggota';
    }

    $children = $childrenMap[$personId] ?? [];
    if (! is_array($children)) {
        $children = [];
    }
    usort($children, function ($a, $b): int {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    $directChildIds = [];
    foreach ($children as $childRow) {
        if (! is_array($childRow)) {
            continue;
        }
        $childId = trim((string) ($childRow['id'] ?? ''));
        if ($childId === '') {
            continue;
        }
        $directChildIds[$childId] = true;
    }

    $leaderGroups = $groupsByLeader[$personId] ?? [];
    if (! is_array($leaderGroups)) {
        $leaderGroups = [];
    }
    usort($leaderGroups, function ($a, $b): int {
        $aTime = trim((string) ($a['created_at'] ?? ''));
        $bTime = trim((string) ($b['created_at'] ?? ''));
        if ($aTime !== $bTime) {
            return strcmp($aTime, $bTime);
        }
        $aId = trim((string) ($a['id'] ?? ''));
        $bId = trim((string) ($b['id'] ?? ''));
        if ($aId !== $bId) {
            return strcmp($aId, $bId);
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $groupBranches = [];
    $assignedChildIds = [];
    foreach ($leaderGroups as $groupRow) {
        if (! is_array($groupRow)) {
            continue;
        }
        $groupName = trim((string) ($groupRow['name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $isVirtualGroup = ! empty($groupRow['virtual']);
        if ($isRoot && $isVirtualGroup && strcasecmp($groupName, 'kelompok') === 0) {
            $groupName = 'Jalur Pemuridan';
        }
        $progressLabel = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        if ($progressLabel === '') {
            $progressLabel = '-';
        }
        $assistantId = trim((string) ($groupRow['assistant_id'] ?? ''));
        $assistantName = $assistantId !== '' ? person_label($peopleById, $assistantId, '') : '';
        $memberIds = $groupRow['member_ids'] ?? [];
        if (! is_array($memberIds)) {
            $memberIds = [];
        }
        $normalizedMemberIds = [];
        $seenMemberIds = [];
        foreach ($memberIds as $memberIdRaw) {
            $memberId = trim((string) $memberIdRaw);
            if ($memberId === '' || isset($seenMemberIds[$memberId]) || ! isset($peopleById[$memberId])) {
                continue;
            }
            $seenMemberIds[$memberId] = true;
            $normalizedMemberIds[] = $memberId;
            if (isset($directChildIds[$memberId])) {
                $assignedChildIds[$memberId] = true;
            }
        }
        usort($normalizedMemberIds, function (string $a, string $b) use ($peopleById): int {
            $nameA = trim((string) ($peopleById[$a]['name'] ?? ''));
            $nameB = trim((string) ($peopleById[$b]['name'] ?? ''));

            return strcasecmp($nameA, $nameB);
        });
        $groupBranches[] = [
            'id' => trim((string) ($groupRow['id'] ?? '')),
            'name' => $groupName,
            'progress' => $progressLabel,
            'assistant_id' => $assistantId,
            'assistant_name' => $assistantName,
            'notes' => str_replace(["\r", "\n"], ' ', (string) ($groupRow['notes'] ?? '')),
            'member_ids' => $normalizedMemberIds,
            'is_virtual' => $isVirtualGroup,
            'is_ungrouped' => false,
            'parent_group_id' => trim((string) ($groupRow['parent_group_id'] ?? '')),
            'status' => strtolower(trim((string) ($groupRow['status'] ?? 'active'))),
            'created_at' => trim((string) ($groupRow['created_at'] ?? '')),
            'child_groups' => [],
        ];
    }

    $ungroupedMemberIds = [];
    foreach (array_keys($directChildIds) as $childId) {
        if (isset($assignedChildIds[$childId])) {
            continue;
        }
        $ungroupedMemberIds[] = $childId;
    }
    if (! $isRoot && count($ungroupedMemberIds) > 0) {
        usort($ungroupedMemberIds, function (string $a, string $b) use ($peopleById): int {
            $nameA = trim((string) ($peopleById[$a]['name'] ?? ''));
            $nameB = trim((string) ($peopleById[$b]['name'] ?? ''));

            return strcasecmp($nameA, $nameB);
        });
        $groupBranches[] = [
            'id' => '',
            'name' => 'Tanpa Kelompok',
            'progress' => '-',
            'assistant_id' => '',
            'assistant_name' => '',
            'notes' => '',
            'member_ids' => $ungroupedMemberIds,
            'is_virtual' => false,
            'is_ungrouped' => true,
            'parent_group_id' => '',
            'status' => 'active',
            'created_at' => '',
            'child_groups' => [],
        ];
    }

    $groupBranchIndexById = [];
    foreach ($groupBranches as $groupBranchIndex => $groupBranch) {
        if (! is_array($groupBranch)) {
            continue;
        }
        $groupId = trim((string) ($groupBranch['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $groupBranchIndexById[$groupId] = $groupBranchIndex;
    }
    $topLevelGroupBranches = [];
    foreach ($groupBranches as $groupBranch) {
        if (! is_array($groupBranch)) {
            continue;
        }
        $parentGroupId = trim((string) ($groupBranch['parent_group_id'] ?? ''));
        if ($parentGroupId !== '' && isset($groupBranchIndexById[$parentGroupId])) {
            $parentIndex = $groupBranchIndexById[$parentGroupId];
            $groupBranches[$parentIndex]['child_groups'][] = $groupBranch;

            continue;
        }
        $topLevelGroupBranches[] = $groupBranch;
    }
    foreach ($topLevelGroupBranches as &$topLevelGroupBranch) {
        $topLevelGroupBranch = attach_people_tree_group_children($topLevelGroupBranch, $groupBranches);
    }
    unset($topLevelGroupBranch);
    $groupBranches = $topLevelGroupBranches;

    $personMetaParts = [];
    if ($isRoot) {
        $personMetaParts[] = 'Akar Pemuridan';
    } else {
        $personMetaParts[] = $role;
    }
    if (count($groupBranches) > 0) {
        $personMetaParts[] = (string) count($groupBranches).' kelompok';
    }
    if (count($directChildIds) > 0) {
        $personMetaParts[] = (string) count($directChildIds).' anggota';
    } elseif (! $isRoot) {
        $personMetaParts[] = 'Belum ada binaan';
    }
    $personMetaLabel = implode(' • ', $personMetaParts);

    $personGender = normalize_member_gender_value((string) ($person['gender'] ?? ''));
    if ($personGender === '') {
        $personMemberId = trim((string) ($person['member_id'] ?? ''));
        if ($personMemberId !== '' && isset($membersById[$personMemberId])) {
            $personGender = normalize_member_gender_value((string) ($membersById[$personMemberId]['gender'] ?? ''));
        }
    }

    $leaderIds = get_parent_ids($person);
    $leader1 = trim((string) ($leaderIds[0] ?? ''));
    $attrName = str_replace(["\r", "\n"], ' ', (string) ($person['name'] ?? ''));
    $attrPhone = str_replace(["\r", "\n"], ' ', (string) ($person['phone'] ?? ''));
    $attrNotes = str_replace(["\r", "\n"], ' ', (string) ($person['notes'] ?? ''));
    $attrMemberId = trim((string) ($person['member_id'] ?? ''));
    $canPersonManage = $canManageTree;

    $personItemClass = 'tree-v2-item tree-v2-item-person'.($isRoot ? ' is-root' : '');
    $personNodeClass = 'tree-v2-node tree-v2-person'.($isRoot ? ' is-root' : '');
    if ($personGender === 'Laki-laki') {
        $personNodeClass .= ' is-male';
    } elseif ($personGender === 'Perempuan') {
        $personNodeClass .= ' is-female';
    }
    if ($canPersonManage) {
        $personNodeClass .= ' is-actionable';
    }
    $personNodeAttrs = '';
    if ($canPersonManage) {
        $personNodeAttrs .= ' data-tree-v2-node-action="person"';
        $personNodeAttrs .= ' data-person-id="'.h($personId).'"';
        $personNodeAttrs .= ' data-member-id="'.h($attrMemberId).'"';
        $personNodeAttrs .= ' data-name="'.h($attrName).'"';
        $personNodeAttrs .= ' data-phone="'.h($attrPhone).'"';
        $personNodeAttrs .= ' data-notes="'.h($attrNotes).'"';
        $personNodeAttrs .= ' data-leader1-id="'.h($leader1).'"';
        $personNodeAttrs .= ' data-is-root="'.($isRoot ? '1' : '0').'"';
        $personNodeAttrs .= ' tabindex="0" role="button" aria-label="Aksi untuk '.h($personName).'"';
    }
    $personNodeAttrs .= ' data-search-name="'.h($attrName).'"';
    echo '<li class="'.h($personItemClass).'" style="--tree-v2-depth:'.h((string) max(0, $depth)).";\">\n";
    echo '  <article class="'.h($personNodeClass).'"'.$personNodeAttrs.">\n";
    echo "    <div class=\"tree-v2-node-head\">\n";
    echo '      <div class="tree-v2-name">'.h($personName)."</div>\n";
    if ($isRoot) {
        echo "      <span class=\"badge warning\">Akar</span>\n";
    } else {
        echo '      <span class="badge muted">'.h($role)."</span>\n";
    }
    echo "    </div>\n";
    echo '    <div class="tree-v2-meta">'.h($personMetaLabel)."</div>\n";
    echo "  </article>\n";

    if (count($groupBranches) > 0) {
        echo "  <ul class=\"tree-v2-children tree-v2-level-groups\">\n";
        foreach ($groupBranches as $groupBranch) {
            if (! is_array($groupBranch)) {
                continue;
            }
            render_people_tree_v3_group_branch(
                $groupBranch,
                $peopleById,
                $childrenMap,
                $groupsByLeader,
                $membersById,
                $rootLeaderId,
                $stack,
                $depth + 1,
                $canManageTree,
                $personId,
                $personName
            );
        }
        echo "  </ul>\n";
    }

    if ($isRoot && count($ungroupedMemberIds) > 0) {
        echo "  <ul class=\"tree-v2-children tree-v2-level-members\">\n";
        foreach ($ungroupedMemberIds as $memberId) {
            render_people_tree_v3($memberId, $peopleById, $childrenMap, $groupsByLeader, $membersById, $rootLeaderId, $stack, $depth + 1, $canManageTree);
        }
        echo "  </ul>\n";
    }

    echo "</li>\n";
}

function render_worship_penatalayan_schedule_png(array $schedule): ?string
{
    if (! function_exists('imagecreatetruecolor')) {
        return null;
    }

    $weekDates = is_array($schedule['week_dates'] ?? null) ? $schedule['week_dates'] : [];
    $rows = is_array($schedule['rows'] ?? null) ? $schedule['rows'] : [];
    $weekCount = max(1, count($weekDates));
    $margin = 24;
    $roleColumnWidth = 180;
    $weekColumnWidth = $weekCount >= 5 ? 132 : 148;
    $tableWidth = $roleColumnWidth + ($weekCount * $weekColumnWidth);

    $titleText = trim((string) ($schedule['title'] ?? default_worship_penatalayan_title((string) ($schedule['month'] ?? date('Y-m')))));
    if ($titleText === '') {
        $titleText = default_worship_penatalayan_title((string) ($schedule['month'] ?? date('Y-m')));
    }
    $updateText = trim((string) ($schedule['update_note'] ?? ''));
    $titleLines = worship_penatalayan_svg_wrap_lines($titleText, 54);
    $updateLines = $updateText !== '' ? worship_penatalayan_svg_wrap_lines($updateText, 48) : [];
    $titleBlockHeight = 26 + (count($titleLines) * 28) + (count($updateLines) > 0 ? 12 + (count($updateLines) * 18) : 0);

    $fontRegular = worship_penatalayan_font_path(false);
    $fontBold = worship_penatalayan_font_path(true);
    if ($fontBold === '') {
        $fontBold = $fontRegular;
    }

    $headerHeight = 50;
    $rowLayouts = [];
    foreach ($rows as $row) {
        if (! is_array($row)) {
            continue;
        }
        $roleLabel = trim((string) ($row['role'] ?? '-'));
        if ($roleLabel === '') {
            $roleLabel = '-';
        }
        $roleLayout = worship_penatalayan_png_text_layout($roleLabel, $roleColumnWidth - 18, 13, $fontBold, 16);
        $assignments = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
        $cellLayoutsByWeek = [];
        $maxBlockHeight = (float) ($roleLayout['height'] ?? 16);
        $isTrainingSchedule = strtolower($roleLabel) === 'jadwal latihan';
        for ($weekIndex = 0; $weekIndex < $weekCount; $weekIndex++) {
            $cellValue = trim((string) ($assignments[$weekIndex] ?? ''));
            if ($isTrainingSchedule) {
                $cellValue = worship_penatalayan_training_label($cellValue, (string) ($schedule['month'] ?? ''));
            }
            $cellLayout = $cellValue !== ''
                ? worship_penatalayan_png_text_layout(
                    $cellValue,
                    $weekColumnWidth - 16,
                    $isTrainingSchedule ? 11 : 13,
                    $fontRegular,
                    $isTrainingSchedule ? 13 : 16,
                    $isTrainingSchedule ? 0 : 4
                )
                : ['lines' => [''], 'extra_gaps' => [], 'height' => 16];
            $cellLayoutsByWeek[] = $cellLayout;
            $maxBlockHeight = max($maxBlockHeight, (float) ($cellLayout['height'] ?? 16));
        }
        $rowHeight = max(34, (int) ceil($maxBlockHeight + 10));
        $rowLayouts[] = [
            'role' => $roleLabel,
            'role_layout' => $roleLayout,
            'cell_layouts' => $cellLayoutsByWeek,
            'height' => $rowHeight,
        ];
    }

    $tableHeight = $headerHeight;
    foreach ($rowLayouts as $layout) {
        $tableHeight += (int) ($layout['height'] ?? 0);
    }
    $imageWidth = ($margin * 2) + $tableWidth;
    $imageHeight = ($margin * 2) + $titleBlockHeight + 18 + $tableHeight;

    $image = imagecreatetruecolor($imageWidth, $imageHeight);
    if ($image === false) {
        return null;
    }
    imageantialias($image, true);
    $white = imagecolorallocate($image, 255, 255, 255);
    $titleBg = imagecolorallocate($image, 229, 231, 235);
    $headerBg = imagecolorallocate($image, 209, 213, 219);
    $roleBg = imagecolorallocate($image, 229, 231, 235);
    $bodyBg = imagecolorallocate($image, 243, 244, 246);
    $trainingBg = imagecolorallocate($image, 254, 243, 199);
    $dark = imagecolorallocate($image, 17, 24, 39);
    $muted = imagecolorallocate($image, 71, 85, 105);
    imagefill($image, 0, 0, $white);

    imagefilledrectangle($image, $margin, $margin, $margin + $tableWidth, $margin + $titleBlockHeight, $titleBg);

    $titleY = $margin + 18;
    worship_penatalayan_png_draw_text($image, $titleLines, $margin + ($tableWidth / 2), $titleY, $dark, [
        'anchor' => 'middle',
        'size' => 24,
        'line_height' => 28,
        'font' => $fontBold,
    ]);
    if (count($updateLines) > 0) {
        $updateY = $titleY + (count($titleLines) * 28) + 8;
        worship_penatalayan_png_draw_text($image, $updateLines, $margin + ($tableWidth / 2), $updateY, $muted, [
            'anchor' => 'middle',
            'size' => 16,
            'line_height' => 18,
            'font' => $fontRegular,
        ]);
    }

    $tableX = $margin;
    $tableY = $margin + $titleBlockHeight + 18;
    imagefilledrectangle($image, $tableX, $tableY, $tableX + $tableWidth, $tableY + $tableHeight, $bodyBg);
    imagefilledrectangle($image, $tableX, $tableY, $tableX + $tableWidth, $tableY + $headerHeight, $headerBg);

    $rowY = $tableY + $headerHeight;
    foreach ($rowLayouts as $layout) {
        $rowHeight = (int) ($layout['height'] ?? 34);
        $roleLabel = strtolower((string) ($layout['role'] ?? ''));
        imagefilledrectangle($image, $tableX, $rowY, $tableX + $tableWidth, $rowY + $rowHeight, $bodyBg);
        if ($roleLabel === 'jadwal latihan') {
            imagefilledrectangle($image, $tableX, $rowY, $tableX + $tableWidth, $rowY + $rowHeight, $trainingBg);
        } else {
            imagefilledrectangle($image, $tableX, $rowY, $tableX + $roleColumnWidth, $rowY + $rowHeight, $roleBg);
        }
        $rowY += $rowHeight;
    }

    imagesetthickness($image, 1);
    imagerectangle($image, $tableX, $tableY, $tableX + $tableWidth, $tableY + $tableHeight, $dark);
    $roleBoundaryX = $tableX + $roleColumnWidth;
    imageline($image, $roleBoundaryX, $tableY, $roleBoundaryX, $tableY + $tableHeight, $dark);
    for ($weekIndex = 1; $weekIndex < $weekCount; $weekIndex++) {
        $colX = $tableX + $roleColumnWidth + ($weekIndex * $weekColumnWidth);
        imageline($image, $colX, $tableY, $colX, $tableY + $tableHeight, $dark);
    }
    $currentY = $tableY + $headerHeight;
    imageline($image, $tableX, $currentY, $tableX + $tableWidth, $currentY, $dark);
    foreach ($rowLayouts as $layout) {
        $currentY += (int) ($layout['height'] ?? 0);
        imageline($image, $tableX, $currentY, $tableX + $tableWidth, $currentY, $dark);
    }

    $headerRoleLayout = worship_penatalayan_png_text_layout('PELAYAN', $roleColumnWidth - 10, 14, $fontBold, 16);
    worship_penatalayan_png_draw_text($image, $headerRoleLayout['lines'], $tableX + ($roleColumnWidth / 2), $tableY + (($headerHeight - $headerRoleLayout['height']) / 2), $dark, [
        'anchor' => 'middle',
        'size' => 14,
        'line_height' => 16,
        'font' => $fontBold,
        'extra_gaps' => $headerRoleLayout['extra_gaps'],
    ]);
    foreach ($weekDates as $weekIndex => $weekDate) {
        $colX = $tableX + $roleColumnWidth + ($weekIndex * $weekColumnWidth);
        $headerDateLayout = worship_penatalayan_png_text_layout(format_short_indo_weekday_date((string) $weekDate), $weekColumnWidth - 10, 12, $fontBold, 16);
        worship_penatalayan_png_draw_text($image, $headerDateLayout['lines'], $colX + ($weekColumnWidth / 2), $tableY + (($headerHeight - $headerDateLayout['height']) / 2), $dark, [
            'anchor' => 'middle',
            'size' => 12,
            'line_height' => 16,
            'font' => $fontBold,
            'extra_gaps' => $headerDateLayout['extra_gaps'],
        ]);
    }

    $rowTextY = $tableY + $headerHeight;
    foreach ($rowLayouts as $layout) {
        $rowHeight = (int) ($layout['height'] ?? 34);
        $roleLayout = is_array($layout['role_layout'] ?? null) ? $layout['role_layout'] : ['lines' => [''], 'extra_gaps' => [], 'height' => 16];
        worship_penatalayan_png_draw_text($image, $roleLayout['lines'], $tableX + 10, $rowTextY + (($rowHeight - (float) ($roleLayout['height'] ?? 16)) / 2), $dark, [
            'anchor' => 'start',
            'size' => 13,
            'line_height' => 16,
            'font' => $fontBold,
            'extra_gaps' => $roleLayout['extra_gaps'] ?? [],
        ]);
        foreach ($layout['cell_layouts'] ?? [] as $weekIndex => $cellLayout) {
            $colX = $tableX + $roleColumnWidth + ($weekIndex * $weekColumnWidth);
            $cellLayout = is_array($cellLayout) ? $cellLayout : ['lines' => [''], 'extra_gaps' => [], 'height' => 16];
            $isTrainingSchedule = strtolower((string) ($layout['role'] ?? '')) === 'jadwal latihan';
            worship_penatalayan_png_draw_text($image, $cellLayout['lines'], $colX + ($weekColumnWidth / 2), $rowTextY + (($rowHeight - (float) ($cellLayout['height'] ?? 16)) / 2), $dark, [
                'anchor' => 'middle',
                'size' => $isTrainingSchedule ? 11 : 13,
                'line_height' => $isTrainingSchedule ? 13 : 16,
                'font' => $fontRegular,
                'extra_gaps' => $cellLayout['extra_gaps'] ?? [],
            ]);
        }
        $rowTextY += $rowHeight;
    }

    ob_start();
    imagepng($image);
    $pngBinary = ob_get_clean();
    imagedestroy($image);

    return is_string($pngBinary) ? $pngBinary : null;
}

function page_header_plain(string $title, array $settings, string $bodyClass = ''): void
{
    $app = h($settings['church_name'] ?? app_church_name());
    $bodyClasses = ['app-page'];
    append_body_classes($bodyClasses, $bodyClass);
    $classAttr = body_class_attr($bodyClasses);
    render_app_document_head($app);
    echo '<body'.$classAttr.">\n";
    echo "<main class=\"container\">\n";
}

function page_header(string $title, array $settings, string $currentPage, bool $showTitle = true, string $bodyClass = ''): void
{
    $app = h($settings['church_name'] ?? app_church_name());
    $currentBranch = current_user_branch();
    $currentScope = current_auth_access_scope();
    $isCentralReadonlySession = is_effective_central_discipleship_readonly();
    $worshipOnlyAccess = current_user_can_access_worship();
    $worshipScopeWithoutFeature = is_worship_only_scope($currentScope) && ! $worshipOnlyAccess;
    $discipleshipOnlyAccess = is_discipleship_branch_scope($currentScope) || $isCentralReadonlySession;
    $hideMemberDataFeatures = false;
    $bodyClasses = ['app-page'];
    if ($isCentralReadonlySession) {
        $bodyClasses[] = 'is-central-readonly';
    }
    $currentPageClass = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($currentPage)));
    if (is_string($currentPageClass) && $currentPageClass !== '') {
        $bodyClasses[] = 'page-'.$currentPageClass;
    }
    append_body_classes($bodyClasses, $bodyClass);
    $classAttr = body_class_attr($bodyClasses);
    $activeGroup = page_header_active_group($currentPage);
    $showCentralToolbar = $isCentralReadonlySession && $activeGroup === 'pemuridan';
    render_app_document_head($app);
    echo '<body'.$classAttr.">\n";
    echo "<div class=\"app-shell\">\n";
    echo "  <button class=\"sidebar-toggle\" type=\"button\" data-sidebar-toggle aria-controls=\"app-sidebar\" aria-expanded=\"false\">Menu</button>\n";
    echo "  <button class=\"sidebar-backdrop\" type=\"button\" data-sidebar-backdrop aria-label=\"Tutup menu\"></button>\n";
    echo "  <aside class=\"sidebar\" id=\"app-sidebar\">\n";
    echo "    <div class=\"sidebar-brand\">\n";
    echo '      <div class="brand">'.$app."</div>\n";
    echo '      <div class="brand-sub" data-live-jakarta-time>'.h(current_jakarta_time_label())."</div>\n";
    echo "    </div>\n";
    echo "    <div class=\"sidebar-section\">\n";
    echo "      <div class=\"sidebar-label\">Fitur</div>\n";
    echo "      <nav class=\"sidebar-nav\">\n";
    render_sidebar_navigation($currentPage, $currentBranch, $discipleshipOnlyAccess, $worshipOnlyAccess, $activeGroup, $hideMemberDataFeatures, $worshipScopeWithoutFeature);
    echo "      </nav>\n";
    echo "    </div>\n";
    echo '    <form method="post" action="'.h(route('auth.logout'))."\" class=\"sidebar-section\">\n";
    echo '      '.csrf_field()."\n";
    echo "      <input type=\"hidden\" name=\"action\" value=\"logout\">\n";
    echo "      <button class=\"nav-item button\" type=\"submit\">Keluar</button>\n";
    echo "    </form>\n";
    echo "  </aside>\n";
    echo "  <div class=\"app-main\">\n";
    echo "    <main class=\"container\">\n";
    if (function_exists('is_developer_session') && is_developer_session() && function_exists('developer_debug_banner_enabled') && developer_debug_banner_enabled()) {
        echo '  <div class="developer-debug-banner">Developer debug aktif &middot; cabang '.h(user_branch_label($currentBranch))."</div>\n";
    }
    $showCentralToolbarBeforeTitle = $showCentralToolbar && $showTitle;
    if ($showTitle && ! $showCentralToolbarBeforeTitle) {
        echo '  <h1>'.h($title)."</h1>\n";
    }
    if ($showCentralToolbar) {
        render_central_rekap_toolbar($currentPage);
    }
    if ($showCentralToolbarBeforeTitle) {
        echo "  <div class=\"card-row discipleship-page-head central-page-head\">\n";
        echo '    <h1>'.h($title)."</h1>\n";
        echo "  </div>\n";
    }
}

function page_footer_plain(): void
{
    echo "</main>\n";
    render_app_script_tag();
    echo "</body>\n";
    echo "</html>\n";
}

function page_footer(): void
{
    echo "    </main>\n";
    echo "  </div>\n";
    echo "</div>\n";
    render_app_script_tag();
    echo "</body>\n";
    echo "</html>\n";
}
