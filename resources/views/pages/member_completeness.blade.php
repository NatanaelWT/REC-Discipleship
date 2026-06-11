<?php

if ($page === 'member_completeness') {
    page_header('Kelengkapan Data Jemaat', $settings, $page, false, 'page-member-completeness page-member-table-scroll');

    render_condition_alerts([
        ['when' => isset($_GET['saved']), 'tone' => 'success', 'message' => 'Data jemaat berhasil disimpan.'],
    ]);

    $error = trim((string) ($_GET['error'] ?? ''));
    render_mapped_error_alert($error, member_form_error_messages());

    $visibleCompletenessFieldKeys = [
        'full_name' => true,
        'gender' => true,
        'birth' => true,
        'birth_year' => true,
        'whatsapp' => true,
        'photos' => true,
    ];
    $filterOptions = member_completeness_filter_options();
    $missingFilter = trim((string) ($_GET['missing'] ?? 'all'));
    if (!isset($filterOptions[$missingFilter])) {
        $missingFilter = 'all';
    }

    $membersById = index_by_id($members);
    $editId = trim((string) ($_GET['edit'] ?? ''));
    $editMember = $editId !== '' ? ($membersById[$editId] ?? null) : null;
    $autoOpenEditId = '';
    if ($editMember !== null && is_member_active($editMember)) {
        $autoOpenEditId = $editId;
    } elseif ($editId !== '' && $error === '') {
        echo "<div class=\"alert danger\">Data jemaat yang ingin diedit tidak ditemukan.</div>\n";
    }

    $activeMembers = filter_active_members($members);
    usort($activeMembers, function ($a, $b) {
        return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
    });
    $fieldTemplate = member_completeness_fields([]);
    $trackedFieldTemplate = [];
    foreach ($fieldTemplate as $fieldKey => $fieldMeta) {
        if (!isset($visibleCompletenessFieldKeys[$fieldKey])) {
            continue;
        }
        $trackedFieldTemplate[$fieldKey] = $fieldMeta;
    }
    $missingFieldCounts = [];
    foreach (array_keys($trackedFieldTemplate) as $fieldKey) {
        $missingFieldCounts[$fieldKey] = 0;
    }
    $completeMembersCount = 0;
    $incompleteMembersCount = 0;

    $preparedRows = [];
    $memberEditModalTemplates = [];
    foreach ($activeMembers as $member) {
        $memberId = trim((string) ($member['id'] ?? ''));
        $fullName = trim((string) ($member['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = '-';
        }
        if ($memberId !== '') {
            $memberEditModalTemplates[$memberId] = [
                'title' => 'Edit Jemaat: ' . $fullName,
                'content' => render_member_form_html($member, 'data-member-edit-close', 'member_completeness', $missingFilter),
            ];
        }

        $memberFields = member_completeness_fields($member);
        $missingItems = [];
        foreach ($memberFields as $fieldKey => $fieldMeta) {
            if (!isset($visibleCompletenessFieldKeys[$fieldKey])) {
                continue;
            }
            $isFilled = !empty($fieldMeta['filled']);
            if ($isFilled) {
                continue;
            }
            $missingLabel = trim((string) ($fieldMeta['label'] ?? $fieldKey));
            if ($missingLabel === '') {
                $missingLabel = $fieldKey;
            }
            $missingItems[$fieldKey] = $missingLabel;
            if (isset($missingFieldCounts[$fieldKey])) {
                $missingFieldCounts[$fieldKey]++;
            }
        }
        if (count($missingItems) === 0) {
            $completeMembersCount++;
        } else {
            $incompleteMembersCount++;
        }

        $matchesFilter = false;
        if ($missingFilter === 'all') {
            $matchesFilter = true;
        } else {
            $matchesFilter = isset($missingItems[$missingFilter]);
        }
        if (!$matchesFilter) {
            continue;
        }

        $birthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
        $birthDayMonth = member_birth_day_month($member);
        $birthLabel = '-';
        if ($birthDate !== '') {
            $birthLabel = format_indo_date($birthDate);
        } elseif ($birthDayMonth !== '') {
            $birthLabel = format_member_birth_day_month($birthDayMonth) . ' (tanpa tahun)';
        }

        $whatsapp = trim((string) ($member['whatsapp'] ?? ''));
        $waDisplay = $whatsapp !== '' ? $whatsapp : '-';
        $waDigits = normalize_whatsapp_digits($whatsapp);
        $waHtml = h($waDisplay);
        if ($waDigits !== '') {
            $waHtml = "<a class=\"note-link\" href=\"" . h('https://wa.me/' . $waDigits) . "\" target=\"_blank\" rel=\"noopener\">" . h($waDisplay) . "</a>";
        }

        $photoLinks = [];
        $photoNumber = 0;
        foreach (extract_member_photos($member) as $photo) {
            $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($photoPath === '') {
                continue;
            }
            $photoUrl = secure_upload_url($photoPath);
            if ($photoUrl === '') {
                continue;
            }
            $photoNumber++;
            $photoLabel = 'Foto ' . (string) $photoNumber;
            $photoLinks[] = "<a class=\"note-link\" href=\"" . h($photoUrl) . "\" target=\"_blank\" rel=\"noopener\">" . h($photoLabel) . "</a>";
        }
        $photoHtml = count($photoLinks) > 0
            ? "<div class=\"member-photo-links\">" . implode(' ', $photoLinks) . "</div>"
            : '-';

        $missingBadges = [];
        foreach ($missingItems as $missingLabel) {
            $missingBadges[] = "<span class=\"badge muted\">" . h($missingLabel) . "</span>";
        }
        $missingHtml = count($missingBadges) > 0
            ? implode(' ', $missingBadges)
            : "<span class=\"badge success\">Lengkap</span>";
        $editHtml = $memberId !== ''
            ? "<button class=\"btn tiny icon-btn\" type=\"button\" data-member-edit-open=\"" . h($memberId) . "\" aria-label=\"Edit\" title=\"Edit\">" . icon_svg('edit') . "</button>"
            : '-';

        $preparedRows[] = [
            'full_name' => $fullName,
            'birth_label' => $birthLabel,
            'wa_html' => $waHtml,
            'photo_html' => $photoHtml,
            'missing_html' => $missingHtml,
            'edit_html' => $editHtml,
        ];
    }

    $filteredCount = count($preparedRows);
    $activeMembersCount = count($activeMembers);
    $currentFilterLabel = trim((string) ($filterOptions[$missingFilter] ?? 'Semua Jemaat Aktif'));
    if ($currentFilterLabel === '') {
        $currentFilterLabel = 'Semua Jemaat Aktif';
    }

    echo "<section class=\"card msk-hero-card members-hero-card member-completeness-hero-card\">\n";
    echo "  <div class=\"msk-hero-head\">\n";
    echo "    <div class=\"msk-hero-copy\">\n";
    echo "      <span class=\"msk-hero-kicker\">Data Jemaat</span>\n";
    echo "      <h1>Kelengkapan Data Jemaat</h1>\n";
    echo "      <p>Pantau data jemaat yang belum lengkap, fokus ke field yang masih kosong, lalu perbarui langsung dari satu tabel yang lebih ringkas.</p>\n";
    echo "    </div>\n";
    echo "    <div class=\"msk-hero-stats\" aria-label=\"Ringkasan kelengkapan data jemaat\">\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Filter</span><strong class=\"msk-hero-stat-value\">" . h($currentFilterLabel) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Jemaat Aktif</span><strong class=\"msk-hero-stat-value\">" . h((string) $activeMembersCount) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Lengkap</span><strong class=\"msk-hero-stat-value\">" . h((string) $completeMembersCount) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Perlu Update</span><strong class=\"msk-hero-stat-value\">" . h((string) $incompleteMembersCount) . "</strong></div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <div class=\"actions table-tools members-table-tools msk-hero-tools member-completeness-hero-tools\">\n";
    echo "    <div class=\"msk-hero-controls member-completeness-hero-controls\">\n";
    echo "      <form method=\"get\" class=\"form-row member-completeness-filter-form\">\n";
    echo "        <input type=\"hidden\" name=\"page\" value=\"member_completeness\">\n";
    echo "        <select name=\"missing\" class=\"member-completeness-filter-select\" aria-label=\"Filter kelengkapan data jemaat\" onchange=\"this.form.submit()\">\n";
    echo "          <option value=\"all\" " . ($missingFilter === 'all' ? 'selected' : '') . ">Semua (" . h((string) $activeMembersCount) . ")</option>\n";
    foreach ($trackedFieldTemplate as $fieldKey => $fieldMeta) {
        if (!isset($filterOptions[$fieldKey])) {
            continue;
        }
        $fieldCount = (int) ($missingFieldCounts[$fieldKey] ?? 0);
        if ($fieldCount <= 0 && $missingFilter !== $fieldKey) {
            continue;
        }
        $fieldLabel = trim((string) ($fieldMeta['label'] ?? $fieldKey));
        if ($fieldLabel === '') {
            $fieldLabel = $fieldKey;
        }
        $selected = $missingFilter === $fieldKey ? 'selected' : '';
        echo "          <option value=\"" . h($fieldKey) . "\" " . $selected . ">" . h($fieldLabel . ' (' . (string) $fieldCount . ')') . "</option>\n";
    }
    echo "        </select>\n";
    echo "      </form>\n";
    echo "    </div>\n";
    echo "    <div class=\"msk-hero-search-wrap member-completeness-hero-search-wrap\">\n";
    render_table_search_input('member-completeness-table', 'Cari jemaat aktif...', 'search members-table-search', '', '      ');
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<section class=\"card table-card-plain member-completeness-main-table\">\n";
    echo "  <div class=\"table-wrap\">\n";
    echo "    <table class=\"table\" id=\"member-completeness-table\">\n";
    echo "      <thead><tr><th>Nama Jemaat</th><th>Data Kurang</th><th>Tanggal Lahir</th><th>WhatsApp</th><th>Foto</th><th class=\"actions-head\">Aksi</th></tr></thead>\n";
    echo "      <tbody>\n";
    foreach ($preparedRows as $row) {
        echo "<tr>";
        echo "<td>" . h((string) ($row['full_name'] ?? '-')) . "</td>";
        echo "<td><div class=\"member-completeness-missing\">" . (string) ($row['missing_html'] ?? '-') . "</div></td>";
        echo "<td>" . h((string) ($row['birth_label'] ?? '-')) . "</td>";
        echo "<td>" . (string) ($row['wa_html'] ?? '-') . "</td>";
        echo "<td>" . (string) ($row['photo_html'] ?? '-') . "</td>";
        echo "<td class=\"actions\">" . (string) ($row['edit_html'] ?? '-') . "</td>";
        echo "</tr>\n";
    }
    if ($filteredCount === 0) {
        $emptyLabel = count($activeMembers) === 0
            ? 'Belum ada data jemaat aktif.'
            : 'Tidak ada jemaat aktif yang cocok dengan filter kelengkapan ini.';
        echo "<tr><td colspan=\"6\">" . h($emptyLabel) . "</td></tr>\n";
    }
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </div>\n";
    echo "</section>\n";

    if (count($memberEditModalTemplates) > 0) {
        echo "<div class=\"is-hidden\" data-member-edit-templates>\n";
        foreach ($memberEditModalTemplates as $templateId => $templateData) {
            $templateTitle = trim((string) ($templateData['title'] ?? 'Edit Jemaat'));
            if ($templateTitle === '') {
                $templateTitle = 'Edit Jemaat';
            }
            $templateContent = (string) ($templateData['content'] ?? '');
            echo "<template data-member-edit-template=\"" . h($templateId) . "\" data-member-edit-template-title=\"" . h($templateTitle) . "\">" . $templateContent . "</template>\n";
        }
        echo "</div>\n";

        echo "<div class=\"modal\" id=\"member-completeness-edit-modal\" data-member-edit-modal data-member-edit-auto-open=\"" . h($autoOpenEditId) . "\" aria-hidden=\"true\" role=\"dialog\" aria-modal=\"true\">\n";
        echo "  <div class=\"modal-card member-view-modal-card msk-form-modal-card\">\n";
        echo "    <div class=\"modal-head\">\n";
        echo "      <div class=\"modal-title\" data-member-edit-title>Edit Jemaat</div>\n";
        echo "      <button class=\"btn tiny ghost\" type=\"button\" data-member-edit-close>&times;</button>\n";
        echo "    </div>\n";
        echo "    <div class=\"modal-body\" data-member-edit-body>\n";
        echo "      <div class=\"panel-note\">Pilih tombol Edit pada baris jemaat untuk membuka form edit.</div>\n";
        echo "    </div>\n";
        echo "  </div>\n";
        echo "</div>\n";
    }

    page_footer();
    legacy_exit();
}
