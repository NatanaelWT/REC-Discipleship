<?php

if ($page === 'member_families') {
    page_header('List Keluarga Jemaat', $settings, $page, false, 'page-member-families page-member-families-scroll');

    $activeMembers = filter_active_members($members);
    $familyGroups = member_family_groups($activeMembers);
    $totalFamilies = count($familyGroups);
    $totalFamilyMembers = count($activeMembers);
    $linkedFamilies = 0;
    foreach ($familyGroups as $group) {
        $memberCount = max(0, (int) ($group['member_count'] ?? 0));
        if ($memberCount > 1) {
            $linkedFamilies++;
        }
    }
    $singleFamilies = max(0, $totalFamilies - $linkedFamilies);

    echo "<section class=\"card msk-hero-card members-hero-card member-family-hero-card\">\n";
    echo "  <div class=\"msk-hero-head\">\n";
    echo "    <div class=\"msk-hero-copy\">\n";
    echo "      <span class=\"msk-hero-kicker\">Data Jemaat</span>\n";
    echo "      <h1>List Keluarga Jemaat</h1>\n";
    echo "      <p>Lihat relasi keluarga jemaat aktif, telusuri anggota per keluarga, dan akses kontak WhatsApp dari satu tampilan yang lebih rapi.</p>\n";
    echo "    </div>\n";
    echo "    <div class=\"msk-hero-stats\" aria-label=\"Ringkasan keluarga jemaat\">\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Jemaat Aktif</span><strong class=\"msk-hero-stat-value\">" . h((string) $totalFamilyMembers) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Total Keluarga</span><strong class=\"msk-hero-stat-value\">" . h((string) $totalFamilies) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Terhubung</span><strong class=\"msk-hero-stat-value\">" . h((string) $linkedFamilies) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Tanpa Relasi</span><strong class=\"msk-hero-stat-value\">" . h((string) $singleFamilies) . "</strong></div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <div class=\"actions member-family-tools msk-hero-tools member-family-hero-tools\">\n";
    echo "    <div class=\"msk-hero-search-wrap member-family-hero-search-wrap\">\n";
    render_table_search_input('member-families-table', 'Cari keluarga / jemaat...', 'search member-family-search', '', '      ');
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<section class=\"card table-card-plain member-families-main-table\">\n";
    echo "  <div class=\"table-wrap\">\n";
    echo "    <table class=\"table\" id=\"member-families-table\">\n";
    echo "      <thead><tr><th>No</th><th>Keluarga</th><th>Jumlah Anggota</th><th>Daftar Anggota</th><th>Kontak WhatsApp</th></tr></thead>\n";
    echo "      <tbody>\n";
    $singleSectionShown = false;
    foreach ($familyGroups as $idx => $group) {
        $familyName = trim((string) ($group['display_name'] ?? ''));
        if ($familyName === '') {
            $familyName = 'Tanpa Nama';
        }
        $memberCount = max(0, (int) ($group['member_count'] ?? 0));
        $isLinkedFamily = $memberCount > 1;

        if (!$isLinkedFamily && !$singleSectionShown && $linkedFamilies > 0) {
            echo "<tr class=\"member-family-divider\"><td colspan=\"5\">Jemaat tanpa relasi keluarga</td></tr>\n";
            $singleSectionShown = true;
        }

        $familyLabel = $isLinkedFamily ? ('Keluarga ' . $familyName) : $familyName;

        $memberItems = [];
        $whatsappItems = [];
        $groupMembers = $group['members'] ?? [];
        if (!is_array($groupMembers)) {
            $groupMembers = [];
        }
        foreach ($groupMembers as $memberIdx => $member) {
            $memberId = trim((string) ($member['id'] ?? ''));
            $fullName = trim((string) ($member['full_name'] ?? ''));
            if ($fullName === '') {
                $fullName = 'Tanpa Nama';
            }

            $memberHref = '?page=members';
            if ($memberId !== '') {
                $memberHref = '?page=members&view=' . rawurlencode($memberId);
            }
            $memberLabel = (string) ($memberIdx + 1) . '. ' . $fullName;
            $memberItems[] = "<a class=\"member-family-member note-link\" href=\"" . h($memberHref) . "\">" . h($memberLabel) . "</a>";

            $whatsapp = trim((string) ($member['whatsapp'] ?? ''));
            if ($whatsapp !== '') {
                $waDigits = preg_replace('/\\D+/', '', $whatsapp) ?? '';
                if ($waDigits !== '' && strpos($waDigits, '0') === 0) {
                    $waDigits = '62' . substr($waDigits, 1);
                }
                $waLabel = $fullName . ' - ' . $whatsapp;
                if ($waDigits !== '') {
                    $whatsappItems[] = "<a class=\"member-family-contact note-link\" href=\"" . h('https://wa.me/' . $waDigits) . "\" target=\"_blank\" rel=\"noopener\">" . h($waLabel) . "</a>";
                } else {
                    $whatsappItems[] = "<span class=\"member-family-contact\">" . h($waLabel) . "</span>";
                }
            }
        }

        $memberListHtml = count($memberItems) > 0
            ? "<div class=\"member-family-members\">" . implode('', $memberItems) . "</div>"
            : "<span class=\"member-family-empty\">-</span>";
        $whatsappListHtml = count($whatsappItems) > 0
            ? "<div class=\"member-family-contacts\">" . implode('', $whatsappItems) . "</div>"
            : "<span class=\"member-family-empty\">-</span>";

        $rowClass = $isLinkedFamily ? 'member-family-row is-linked' : 'member-family-row is-single';

        echo "<tr class=\"" . h($rowClass) . "\">";
        echo "<td class=\"member-family-index\">" . h((string) ($idx + 1)) . "</td>";
        echo "<td><div class=\"member-family-title\">" . h($familyLabel) . "</div></td>";
        echo "<td><span class=\"member-family-count\">" . h((string) $memberCount) . "</span></td>";
        echo "<td>" . $memberListHtml . "</td>";
        echo "<td>" . $whatsappListHtml . "</td>";
        echo "</tr>\n";
    }
    if ($totalFamilies === 0) {
        echo "<tr><td colspan=\"5\">Belum ada data keluarga jemaat aktif.</td></tr>\n";
    }
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </div>\n";
    echo "</section>\n";

    page_footer();
    legacy_exit();
}
