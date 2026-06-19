<?php

function page_header(string $title, array $settings, string $currentPage, bool $showTitle = true, string $bodyClass = ''): void {
    $app = h($settings['church_name'] ?? app_church_name());
    $currentBranch = current_user_branch();
    $currentScope = current_auth_access_scope();
    $isCentralReadonlySession = is_effective_central_discipleship_readonly();
    $worshipOnlyAccess = current_user_can_access_worship();
    $worshipScopeWithoutFeature = is_worship_only_scope($currentScope) && !$worshipOnlyAccess;
    $discipleshipOnlyAccess = is_discipleship_branch_scope($currentScope) || $isCentralReadonlySession;
    $hideMemberDataFeatures = false;
    $bodyClasses = ['app-page'];
    if ($isCentralReadonlySession) {
        $bodyClasses[] = 'is-central-readonly';
    }
    $currentPageClass = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($currentPage)));
    if (is_string($currentPageClass) && $currentPageClass !== '') {
        $bodyClasses[] = 'page-' . $currentPageClass;
    }
    append_body_classes($bodyClasses, $bodyClass);
    $classAttr = body_class_attr($bodyClasses);
    $activeGroup = page_header_active_group($currentPage);
    render_app_document_head($app);
    echo "<body" . $classAttr . ">\n";
    echo "<div class=\"app-shell\">\n";
    echo "  <button class=\"sidebar-toggle\" type=\"button\" data-sidebar-toggle aria-controls=\"app-sidebar\" aria-expanded=\"false\">Menu</button>\n";
    echo "  <button class=\"sidebar-backdrop\" type=\"button\" data-sidebar-backdrop aria-label=\"Tutup menu\"></button>\n";
    echo "  <aside class=\"sidebar\" id=\"app-sidebar\">\n";
    echo "    <div class=\"sidebar-brand\">\n";
    echo "      <div class=\"brand\">" . $app . "</div>\n";
    echo "      <div class=\"brand-sub\" data-live-jakarta-time>" . h(current_jakarta_time_label()) . "</div>\n";
    echo "    </div>\n";
    echo "    <div class=\"sidebar-section\">\n";
    echo "      <div class=\"sidebar-label\">Fitur</div>\n";
    echo "      <nav class=\"sidebar-nav\">\n";
    render_sidebar_navigation($currentPage, $currentBranch, $discipleshipOnlyAccess, $worshipOnlyAccess, $activeGroup, $hideMemberDataFeatures, $worshipScopeWithoutFeature);
    echo "      </nav>\n";
    echo "    </div>\n";
    echo "    <form method=\"post\" action=\"" . h(route('auth.logout')) . "\" class=\"sidebar-section\">\n";
    echo "      <input type=\"hidden\" name=\"action\" value=\"logout\">\n";
    echo "      <button class=\"nav-item button\" type=\"submit\">Keluar</button>\n";
    echo "    </form>\n";
    echo "  </aside>\n";
    echo "  <div class=\"app-main\">\n";
    echo "    <main class=\"container\">\n";
    if (function_exists('is_developer_session') && is_developer_session() && function_exists('developer_debug_banner_enabled') && developer_debug_banner_enabled()) {
        echo "  <div class=\"developer-debug-banner\">Developer debug aktif &middot; cabang " . h(user_branch_label($currentBranch)) . "</div>\n";
    }
    $showCentralToolbarBeforeTitle = $isCentralReadonlySession && $showTitle;
    if ($showTitle && !$showCentralToolbarBeforeTitle) {
        echo "  <h1>" . h($title) . "</h1>\n";
    }
    if ($isCentralReadonlySession) {
        render_central_rekap_toolbar($currentPage);
    }
    if ($showCentralToolbarBeforeTitle) {
        echo "  <div class=\"card-row discipleship-page-head central-page-head\">\n";
        echo "    <h1>" . h($title) . "</h1>\n";
        echo "  </div>\n";
    }
}
