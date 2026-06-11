<?php

if ($page === 'member_birthdays') {
    page_header('Ulang Tahun Jemaat Bulanan', $settings, $page, false);

    $monthFilter = normalize_month_value((string) ($_GET['month'] ?? date('Y-m')));
    $monthNumber = (int) substr($monthFilter, 5, 2);
    if ($monthNumber < 1 || $monthNumber > 12) {
        $monthFilter = date('Y-m');
        $monthNumber = (int) date('m');
    }
    $birthdayRows = [];
    $currentYear = (int) date('Y');
    $birthdaysWithKnownAge = 0;
    $birthdaysWithWhatsapp = 0;
    $memberModalTemplates = [];
    $membersById = index_by_id($members);
    $requestedViewId = trim((string) ($_GET['view'] ?? ''));
    $autoOpenViewId = '';

    foreach ($members as $member) {
        if (normalize_member_status_value((string) ($member['membership_status'] ?? 'active')) !== 'active') {
            continue;
        }
        $memberId = trim((string) ($member['id'] ?? ''));
        $fullName = trim((string) ($member['full_name'] ?? ''));
        if ($memberId === '' || $fullName === '') {
            continue;
        }

        $dayMonth = member_birth_day_month($member);
        if ($dayMonth === '') {
            continue;
        }
        $day = (int) substr($dayMonth, 0, 2);
        $month = (int) substr($dayMonth, 3, 2);
        if ($month !== $monthNumber) {
            continue;
        }

        $birthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
        $yearKnown = $birthDate !== '';
        $ageLabel = '-';
        if ($yearKnown) {
            $birthYear = (int) substr($birthDate, 0, 4);
            if ($birthYear > 0 && $birthYear <= $currentYear) {
                $ageLabel = (string) ($currentYear - $birthYear) . ' tahun';
                $birthdaysWithKnownAge++;
            }
        }

        $whatsapp = trim((string) ($member['whatsapp'] ?? ''));
        $waDisplay = $whatsapp !== '' ? $whatsapp : '-';
        $waDigits = preg_replace('/\\D+/', '', $whatsapp) ?? '';
        if ($waDigits !== '' && strpos($waDigits, '0') === 0) {
            $waDigits = '62' . substr($waDigits, 1);
        }
        $waHtml = h($waDisplay);
        if ($waDigits !== '') {
            $birthdaysWithWhatsapp++;
            $waHtml = "<a class=\"note-link\" href=\"" . h('https://wa.me/' . $waDigits) . "\" target=\"_blank\" rel=\"noopener\">" . h($waDisplay) . "</a>";
        }

        $socialMedia = trim((string) ($member['social_media'] ?? ''));
        $socialHtml = '-';
        if ($socialMedia !== '') {
            $socialHtml = "<a class=\"note-link\" href=\"" . h($socialMedia) . "\" target=\"_blank\" rel=\"noopener\">Buka Link</a>";
        }

        $gender = trim((string) ($member['gender'] ?? ''));
        if ($gender === '') {
            $gender = '-';
        }

        $nameHtml = h($fullName);
        if ($memberId !== '') {
            $nameHtml = "<button class=\"note-link member-inline-trigger\" type=\"button\" data-member-view-open=\"" . h($memberId) . "\" aria-label=\"" . h('Lihat detail jemaat ' . $fullName) . "\" title=\"Lihat detail jemaat\">" . h($fullName) . "</button>";
            $memberModalTemplates[$memberId] = [
                'title' => $fullName,
                'content' => render_member_view_html($member, $membersById),
                'edit_href' => '?page=members&edit=' . rawurlencode($memberId),
            ];
            if ($autoOpenViewId === '' && $requestedViewId !== '' && hash_equals($memberId, $requestedViewId)) {
                $autoOpenViewId = $memberId;
            }
        }

        $birthdayRows[] = [
            'day' => $day,
            'full_name' => $fullName,
            'name_html' => $nameHtml,
            'gender' => $gender,
            'birthday_label' => format_member_birth_day_month($dayMonth),
            'age_label' => $ageLabel,
            'wa_html' => $waHtml,
            'social_html' => $socialHtml,
        ];
    }

    usort($birthdayRows, function ($a, $b) {
        $cmp = ((int) ($a['day'] ?? 0)) <=> ((int) ($b['day'] ?? 0));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
    });

    $totalBirthdays = count($birthdayRows);
    $monthLabel = format_indo_month($monthFilter);

    echo "<section class=\"card msk-hero-card members-hero-card member-birthday-hero-card\">\n";
    echo "  <div class=\"msk-hero-head\">\n";
    echo "    <div class=\"msk-hero-copy\">\n";
    echo "      <span class=\"msk-hero-kicker\">Data Jemaat</span>\n";
    echo "      <h1>Ulang Tahun Jemaat Bulanan</h1>\n";
    echo "      <p>Pantau daftar ulang tahun jemaat per bulan, lihat usia tahun ini, dan akses kontak yang bisa langsung dihubungi.</p>\n";
    echo "    </div>\n";
    echo "    <div class=\"msk-hero-stats\" aria-label=\"Ringkasan ulang tahun jemaat bulanan\">\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Filter</span><strong class=\"msk-hero-stat-value\">" . h($monthLabel) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Ulang Tahun</span><strong class=\"msk-hero-stat-value\">" . h((string) $totalBirthdays) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Usia Tersedia</span><strong class=\"msk-hero-stat-value\">" . h((string) $birthdaysWithKnownAge) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">WhatsApp</span><strong class=\"msk-hero-stat-value\">" . h((string) $birthdaysWithWhatsapp) . "</strong></div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <div class=\"actions table-tools msk-hero-tools member-birthday-hero-tools\">\n";
    echo "    <div class=\"msk-hero-controls member-birthday-hero-controls\">\n";
    echo "      <form method=\"get\" class=\"form-row cash-filter-form member-birthday-month-form\">\n";
    echo "        <input type=\"hidden\" name=\"page\" value=\"member_birthdays\">\n";
    echo "        <input type=\"month\" name=\"month\" value=\"" . h($monthFilter) . "\" required aria-label=\"Filter bulan ulang tahun jemaat\" onchange=\"this.form.submit()\">\n";
    echo "      </form>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<section class=\"card\">\n";
    echo "  <div class=\"table-wrap\">\n";
    echo "    <table class=\"table\">\n";
    echo "      <thead><tr><th>Tanggal Ulang Tahun</th><th>Nama</th><th>Jenis Kelamin</th><th>Usia Tahun Ini</th><th>WhatsApp</th><th>Sosial Media</th></tr></thead>\n";
    echo "      <tbody>\n";
    foreach ($birthdayRows as $row) {
        echo "<tr>";
        echo "<td>" . h((string) ($row['birthday_label'] ?? '-')) . "</td>";
        echo "<td>" . (string) ($row['name_html'] ?? h((string) ($row['full_name'] ?? '-'))) . "</td>";
        echo "<td>" . h((string) ($row['gender'] ?? '-')) . "</td>";
        echo "<td>" . h((string) ($row['age_label'] ?? '-')) . "</td>";
        echo "<td>" . ($row['wa_html'] ?? '-') . "</td>";
        echo "<td>" . ($row['social_html'] ?? '-') . "</td>";
        echo "</tr>\n";
    }
    if ($totalBirthdays === 0) {
        echo "<tr><td colspan=\"6\">Belum ada data ulang tahun untuk bulan ini.</td></tr>\n";
    }
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </div>\n";
    echo "</section>\n";

    if (count($memberModalTemplates) > 0) {
        echo "<div class=\"is-hidden\" data-member-view-templates>\n";
        foreach ($memberModalTemplates as $templateId => $templateData) {
            $templateTitle = trim((string) ($templateData['title'] ?? 'Detail Jemaat'));
            if ($templateTitle === '') {
                $templateTitle = 'Detail Jemaat';
            }
            $templateEditHref = (string) ($templateData['edit_href'] ?? '?page=members');
            $templateContent = (string) ($templateData['content'] ?? '');
            echo "<template data-member-view-template=\"" . h($templateId) . "\" data-member-view-template-title=\"" . h($templateTitle) . "\" data-member-view-template-edit=\"" . h($templateEditHref) . "\">" . $templateContent . "</template>\n";
        }
        echo "</div>\n";

        echo "<div class=\"modal\" id=\"member-view-modal\" data-member-view-modal data-member-view-auto-open=\"" . h($autoOpenViewId) . "\" aria-hidden=\"true\" role=\"dialog\" aria-modal=\"true\">\n";
        echo "  <div class=\"modal-card member-view-modal-card msk-view-modal-card\">\n";
        echo "    <div class=\"modal-head\">\n";
        echo "      <div class=\"modal-title\" data-member-view-title>Detail Jemaat</div>\n";
        echo "      <button class=\"btn tiny ghost\" type=\"button\" data-member-view-close>&times;</button>\n";
        echo "    </div>\n";
        echo "    <div class=\"modal-body\" data-member-view-body>\n";
        echo "      <div class=\"panel-note\">Klik nama jemaat pada tabel untuk membuka detail.</div>\n";
        echo "    </div>\n";
        echo "    <div class=\"modal-actions\">\n";
        echo "      <a class=\"btn tiny is-hidden\" href=\"?page=members\" data-member-view-edit-link>Edit</a>\n";
        echo "      <button class=\"btn ghost\" type=\"button\" data-member-view-close>Tutup</button>\n";
        echo "    </div>\n";
        echo "  </div>\n";
        echo "</div>\n";
        echo "<div class=\"modal\" id=\"member-photo-preview-modal\" data-file-preview-modal aria-hidden=\"true\" role=\"dialog\" aria-modal=\"true\">\n";
        echo "  <div class=\"modal-card file-view-modal-card\">\n";
        echo "    <div class=\"modal-head\">\n";
        echo "      <div class=\"modal-title\" data-file-preview-title>Preview Foto</div>\n";
        echo "      <button class=\"btn tiny ghost\" type=\"button\" data-file-preview-close>&times;</button>\n";
        echo "    </div>\n";
        echo "    <div class=\"modal-body file-view-body\">\n";
        echo "      <div class=\"modal-note is-hidden\" data-file-preview-loading></div>\n";
        echo "      <div class=\"modal-note is-hidden\" data-file-preview-note></div>\n";
        echo "      <pre class=\"file-view-text is-hidden\" data-file-preview-text></pre>\n";
        echo "      <div class=\"file-view-image-wrap is-hidden\" data-file-preview-image-wrap><img class=\"file-view-image\" src=\"\" alt=\"Preview Foto\" data-file-preview-image></div>\n";
        echo "      <div class=\"file-view-embed-wrap is-hidden\" data-file-preview-embed-wrap><iframe class=\"file-view-embed\" src=\"\" loading=\"lazy\" referrerpolicy=\"same-origin\" data-file-preview-embed></iframe></div>\n";
        echo "      <div class=\"modal-actions\">\n";
        echo "        <button class=\"btn ghost\" type=\"button\" data-file-preview-close>Tutup</button>\n";
        echo "      </div>\n";
        echo "    </div>\n";
        echo "  </div>\n";
        echo "</div>\n";
    }

    page_footer();
    legacy_exit();
}
