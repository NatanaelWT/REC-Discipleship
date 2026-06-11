<?php

function render_sidebar_nav_group(string $label, string $groupKey, array $items, string $currentPage, string $activeGroup): void {
    $isOpen = $activeGroup === $groupKey;
    $summaryClass = $isOpen ? 'nav-item active has-sub' : 'nav-item has-sub';
    echo "        <details class=\"nav-group\"" . ($isOpen ? ' open' : '') . ">\n";
    echo "          <summary class=\"" . h($summaryClass) . "\">" . h($label) . "<span class=\"chevron\">&#9662;</span></summary>\n";
    echo "          <div class=\"nav-sub\">\n";
    foreach ($items as $item) {
        $page = trim((string) ($item['page'] ?? ''));
        $href = trim((string) ($item['href'] ?? ''));
        $extraActivePages = $item['active_pages'] ?? [];
        if (!is_array($extraActivePages)) {
            $extraActivePages = [];
        }
        $isActive = $page !== '' && ($page === $currentPage || in_array($currentPage, $extraActivePages, true));
        render_sidebar_nav_link((string) ($item['label'] ?? ''), $href, $isActive, '            ');
    }
    echo "          </div>\n";
    echo "        </details>\n";
}
