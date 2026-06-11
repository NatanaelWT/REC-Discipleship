<?php

if ($page === 'members') {
    page_header('Pendataan Jemaat', $settings, $page, false, 'page-member-table-scroll');

    render_condition_alerts([
        ['when' => isset($_GET['saved']), 'tone' => 'success', 'message' => 'Data jemaat berhasil disimpan.'],
        ['when' => isset($_GET['deleted']), 'tone' => 'success', 'message' => 'Data jemaat berhasil dihapus permanen.'],
        ['when' => isset($_GET['lefted']), 'tone' => 'success', 'message' => 'Jemaat berhasil ditandai sudah keluar.'],
        ['when' => isset($_GET['reactivated']), 'tone' => 'success', 'message' => 'Status jemaat berhasil diaktifkan kembali.'],
    ]);

    $error = trim((string) ($_GET['error'] ?? ''));
    render_mapped_error_alert($error, member_form_error_messages(true));

    $membersById = index_by_id($members);
    $editId = trim((string) ($_GET['edit'] ?? ''));
    $editMember = $editId !== '' ? ($membersById[$editId] ?? null) : null;
    $autoOpenEditId = '';
    if ($editMember !== null) {
        $autoOpenEditId = $editId;
    }
    if ($editId !== '' && $editMember === null && $error === '') {
        echo "<div class=\"alert danger\">Data jemaat yang ingin diedit tidak ditemukan.</div>\n";
    }
    $requestedViewId = trim((string) ($_GET['view'] ?? ''));

    $membersSorted = $members;
    usort($membersSorted, function ($a, $b) {
        return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
    });
    $membersById = index_by_id($membersSorted);

    $activeMembersSorted = [];
    $leftMembersSorted = [];
    foreach ($membersSorted as $member) {
        $status = normalize_member_status_value((string) ($member['membership_status'] ?? 'active'));
        if ($status === 'left') {
            $leftMembersSorted[] = $member;
        } else {
            $activeMembersSorted[] = $member;
        }
    }
    $activeMembersById = index_by_id($activeMembersSorted);
    $autoOpenViewId = '';
    if ($requestedViewId !== '') {
        if (isset($activeMembersById[$requestedViewId])) {
            $autoOpenViewId = $requestedViewId;
        } elseif ($error === '') {
            echo "<div class=\"alert danger\">Data jemaat yang ingin dilihat tidak ditemukan.</div>\n";
        }
    }
    $leftMembers = count($leftMembersSorted);
    $totalMembers = count($activeMembersSorted);
    $totalMemberRecords = count($membersSorted);
    $memberFamilyGroups = member_family_groups($activeMembersSorted);
    $totalFamilies = count($memberFamilyGroups);

    $renderMemberForm = function (array $member, string $closeActionAttr = '') use ($membersSorted): string {
        $memberId = trim((string) ($member['id'] ?? ''));
        $fullName = trim((string) ($member['full_name'] ?? ''));
        $gender = normalize_member_gender_value((string) ($member['gender'] ?? ''));
        $birthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
        $birthDayMonth = normalize_member_birth_day_month_value((string) ($member['birth_day_month'] ?? ''));
        if ($birthDate !== '') {
            $birthDayMonth = '';
        }
        $whatsapp = trim((string) ($member['whatsapp'] ?? ''));
        $birthPlace = trim((string) ($member['birth_place'] ?? ''));
        $address = trim((string) ($member['address'] ?? ''));
        $email = trim((string) ($member['email'] ?? ''));
        $socialMedia = trim((string) ($member['social_media'] ?? ''));

        $familyIds = $member['family_ids'] ?? [];
        if (!is_array($familyIds)) {
            $familyIds = [];
        }
        $familyIds = array_values(array_unique(array_map('strval', $familyIds)));
        $isEditMode = $memberId !== '';

        $photos = [];
        foreach (extract_member_photos($member) as $photo) {
            $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($photoPath === '') {
                continue;
            }
            $photoName = trim((string) ($photo['name'] ?? ''));
            if ($photoName === '') {
                $photoName = 'Foto';
            }
            $photos[] = [
                'path' => $photoPath,
                'name' => $photoName,
            ];
        }

        ob_start();
        echo "<form method=\"post\" enctype=\"multipart/form-data\" class=\"form-grid\">\n";
        echo "  <input type=\"hidden\" name=\"action\" value=\"save_member\">\n";
        echo "  <input type=\"hidden\" name=\"id\" value=\"" . h($memberId) . "\">\n";
        echo "  <label>Nama Lengkap<input type=\"text\" name=\"full_name\" value=\"" . h($fullName) . "\" required></label>\n";
        echo "  <label>Jenis Kelamin<select name=\"gender\" required>";
        echo "<option value=\"\">- Pilih -</option>";
        echo "<option value=\"Laki-laki\" " . ($gender === 'Laki-laki' ? 'selected' : '') . ">Laki-laki</option>";
        echo "<option value=\"Perempuan\" " . ($gender === 'Perempuan' ? 'selected' : '') . ">Perempuan</option>";
        echo "</select></label>\n";
        echo "  <label>Tempat Lahir<input type=\"text\" name=\"birth_place\" value=\"" . h($birthPlace) . "\" placeholder=\"Kota lahir\"></label>\n";
        echo "  <label>Tanggal Lahir<input type=\"date\" name=\"birth_date\" value=\"" . h($birthDate) . "\"></label>\n";
        echo "  <label>Tanggal-Bulan Lahir (tanpa tahun)<input type=\"text\" name=\"birth_day_month\" value=\"" . h($birthDayMonth) . "\" placeholder=\"dd-mm (contoh: 17-08)\" pattern=\"\\d{1,2}-\\d{1,2}\"></label>\n";
        echo "  <label>Alamat<textarea name=\"address\" rows=\"2\" placeholder=\"Alamat domisili\">" . h($address) . "</textarea></label>\n";
        echo "  <label>Email<input type=\"email\" name=\"email\" value=\"" . h($email) . "\" placeholder=\"email@contoh.com\"></label>\n";
        echo "  <label>Nomor WhatsApp<input type=\"text\" name=\"whatsapp\" value=\"" . h($whatsapp) . "\" placeholder=\"08xxxxxxxxxx\"></label>\n";
        echo "  <label>Sosial Media (Link)<input type=\"url\" name=\"social_media\" value=\"" . h($socialMedia) . "\" placeholder=\"https://...\"></label>\n";
        foreach ($familyIds as $familyId) {
            if ($familyId === '') {
                continue;
            }
            echo "  <input type=\"hidden\" name=\"family_ids[]\" value=\"" . h($familyId) . "\">\n";
        }
        echo "  <label>Upload Foto (JPG/PNG/WEBP)<input type=\"file\" name=\"member_photos[]\" accept=\".jpg,.jpeg,.png,.webp\" multiple></label>\n";
        if (count($photos) > 0) {
            echo "  <div class=\"member-photo-list\" style=\"grid-column:1/-1;\">\n";
            echo "    <div class=\"member-photo-current\">Foto saat ini:</div>\n";
            foreach ($photos as $idx => $photo) {
                $photoUrl = secure_upload_url((string) ($photo['path'] ?? ''));
                if ($photoUrl === '') {
                    continue;
                }
                $photoLabel = trim((string) ($photo['name'] ?? ''));
                if ($photoLabel === '') {
                    $photoLabel = 'Foto ' . (string) ($idx + 1);
                }
                echo "    <div class=\"member-photo-item\"><a class=\"note-link\" href=\"" . h($photoUrl) . "\" target=\"_blank\" rel=\"noopener\">" . h($photoLabel) . "</a><label class=\"check-label\"><input type=\"checkbox\" name=\"remove_photo_paths[]\" value=\"" . h((string) ($photo['path'] ?? '')) . "\">Hapus</label></div>\n";
            }
            echo "  </div>\n";
        }
        $memberFormActionsClass = 'form-actions';
        if ($isEditMode || $closeActionAttr !== '') {
            $memberFormActionsClass .= ' member-form-actions is-right';
        }
        echo "  <div class=\"" . h($memberFormActionsClass) . "\">\n";
        echo "    <button class=\"btn\" type=\"submit\">Simpan Jemaat</button>\n";
        echo "  </div>\n";
        echo "</form>\n";

        return (string) ob_get_clean();
    };

    $createMemberData = [
        'id' => '',
        'full_name' => '',
        'gender' => '',
        'birth_date' => '',
        'birth_day_month' => '',
        'whatsapp' => '',
        'birth_place' => '',
        'address' => '',
        'email' => '',
        'social_media' => '',
        'family_ids' => [],
        'photos' => [],
    ];
    $createMemberFormContent = $renderMemberForm($createMemberData, 'data-member-create-close');

    echo "<section class=\"card msk-hero-card members-hero-card\">\n";
    echo "  <div class=\"msk-hero-head\">\n";
    echo "    <div class=\"msk-hero-copy\">\n";
    echo "      <span class=\"msk-hero-kicker\">Data Jemaat</span>\n";
    echo "      <h1>Pendataan Jemaat</h1>\n";
    echo "      <p>Kelola profil jemaat aktif, pantau relasi keluarga, dan jaga basis data cabang tetap rapi dari satu panel yang lebih ringkas.</p>\n";
    echo "    </div>\n";
    echo "    <div class=\"msk-hero-stats\" aria-label=\"Ringkasan pendataan jemaat\">\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Aktif</span><strong class=\"msk-hero-stat-value\">" . h((string) $totalMembers) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Total Data</span><strong class=\"msk-hero-stat-value\">" . h((string) $totalMemberRecords) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Keluarga</span><strong class=\"msk-hero-stat-value\">" . h((string) $totalFamilies) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Keluar</span><strong class=\"msk-hero-stat-value\">" . h((string) $leftMembers) . "</strong></div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <div class=\"actions table-tools members-table-tools msk-hero-tools members-hero-tools\">\n";
    echo "    <div class=\"msk-hero-controls members-hero-controls\">\n";
    echo "      <button class=\"btn tiny icon-btn\" type=\"button\" data-member-create-open aria-label=\"Tambah Jemaat\" title=\"Tambah Jemaat\">" . icon_svg('plus') . "</button>\n";
    echo "    </div>\n";
    echo "    <div class=\"msk-hero-search-wrap members-hero-search-wrap\">\n";
    render_table_search_input('members-table', 'Cari jemaat aktif...', 'search members-table-search', '', '      ');
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<section class=\"card table-card-plain members-main-table\">\n";
    echo "  <div class=\"table-wrap\">\n";
    echo "    <table class=\"table\" id=\"members-table\">\n";
    echo "      <thead><tr><th>Nama Jemaat</th><th>Jenis Kelamin</th><th>Tanggal Lahir</th><th>WhatsApp</th><th class=\"actions-head\">Aksi</th></tr></thead>\n";
    echo "      <tbody>\n";
    $memberModalTemplates = [];
    $memberEditModalTemplates = [];
    foreach ($activeMembersSorted as $member) {
        $memberId = (string) ($member['id'] ?? '');
        $fullName = trim((string) ($member['full_name'] ?? '-'));
        if ($fullName === '') {
            $fullName = '-';
        }
        $gender = trim((string) ($member['gender'] ?? ''));
        if ($gender === '') {
            $gender = '-';
        }
        $birthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
        $birthDayMonth = member_birth_day_month($member);
        $birthDateLabel = '-';
        if ($birthDate !== '') {
            $birthDateLabel = format_indo_date($birthDate);
        } elseif ($birthDayMonth !== '') {
            $birthDateLabel = format_member_birth_day_month($birthDayMonth) . ' (tahun tidak diketahui)';
        }

        $whatsapp = trim((string) ($member['whatsapp'] ?? ''));
        $waDisplay = $whatsapp !== '' ? $whatsapp : '-';
        $waDigits = preg_replace('/\\D+/', '', $whatsapp) ?? '';
        if ($waDigits !== '' && strpos($waDigits, '0') === 0) {
            $waDigits = '62' . substr($waDigits, 1);
        }
        $waHtml = h($waDisplay);
        if ($waDigits !== '') {
            $waHtml = "<a class=\"note-link\" href=\"" . h('https://wa.me/' . $waDigits) . "\" target=\"_blank\" rel=\"noopener\">" . h($waDisplay) . "</a>";
        }

        if ($memberId !== '') {
            $memberModalTemplates[$memberId] = [
                'title' => $fullName,
                'content' => render_member_view_html($member, $membersById),
                'edit_href' => '?page=members&edit=' . rawurlencode($memberId),
            ];
            $memberEditModalTemplates[$memberId] = [
                'title' => 'Edit Jemaat: ' . $fullName,
                'content' => $renderMemberForm($member, 'data-member-edit-close'),
            ];
        }

        echo "<tr>";
        echo "<td>" . h($fullName) . "</td>";
        echo "<td>" . h($gender) . "</td>";
        echo "<td>" . h($birthDateLabel) . "</td>";
        echo "<td>" . $waHtml . "</td>";
        echo "<td class=\"actions\">";
        echo "<button class=\"btn tiny secondary icon-btn\" type=\"button\" data-member-view-open=\"" . h($memberId) . "\" aria-label=\"Lihat\" title=\"Lihat\">" . icon_svg('eye') . "</button>";
        echo "<button class=\"btn tiny icon-btn\" type=\"button\" data-member-edit-open=\"" . h($memberId) . "\" aria-label=\"Edit\" title=\"Edit\">" . icon_svg('edit') . "</button>";
        echo "<form method=\"post\" class=\"inline\" onsubmit=\"var reason=prompt('Tuliskan alasan jemaat keluar:'); if(reason===null){return false;} reason=reason.trim(); if(reason===''){alert('Alasan wajib diisi.'); return false;} this.exit_reason.value=reason; return confirm('Tandai jemaat ini sudah keluar?');\">";
        echo "<input type=\"hidden\" name=\"action\" value=\"mark_member_left\">";
        echo "<input type=\"hidden\" name=\"id\" value=\"" . h($memberId) . "\">";
        echo "<input type=\"hidden\" name=\"exit_reason\" value=\"\">";
        echo "<button class=\"btn tiny secondary icon-btn\" type=\"submit\" aria-label=\"Tandai Keluar\" title=\"Tandai Keluar\">" . icon_svg('exit') . "</button>";
        echo "</form>";
        echo "<form method=\"post\" class=\"inline\" onsubmit=\"return confirm('Hapus permanen data jemaat ini? Tindakan ini tidak dapat dibatalkan.');\">";
        echo "<input type=\"hidden\" name=\"action\" value=\"delete_member\">";
        echo "<input type=\"hidden\" name=\"id\" value=\"" . h($memberId) . "\">";
        echo "<button class=\"btn tiny danger icon-btn\" type=\"submit\" aria-label=\"Hapus\" title=\"Hapus\">" . icon_svg('trash') . "</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>\n";
    }
    if (count($activeMembersSorted) === 0) {
        echo "<tr><td colspan=\"5\">Belum ada data jemaat aktif.</td></tr>\n";
    }
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </div>\n";
    echo "</section>\n";

    foreach ($leftMembersSorted as $member) {
        $memberId = trim((string) ($member['id'] ?? ''));
        if ($memberId === '' || isset($memberEditModalTemplates[$memberId])) {
            continue;
        }
        $fullName = trim((string) ($member['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = '-';
        }
        $memberEditModalTemplates[$memberId] = [
            'title' => 'Edit Jemaat: ' . $fullName,
            'content' => $renderMemberForm($member, 'data-member-edit-close'),
        ];
    }

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
        echo "      <div class=\"panel-note\">Pilih tombol Lihat pada baris jemaat untuk membuka detail.</div>\n";
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

    echo "<div class=\"modal\" id=\"member-create-modal\" data-member-create-modal aria-hidden=\"true\" role=\"dialog\" aria-modal=\"true\">\n";
    echo "  <div class=\"modal-card member-view-modal-card msk-view-modal-card\">\n";
    echo "    <div class=\"modal-head\">\n";
    echo "      <div class=\"modal-title\">Tambah Jemaat</div>\n";
    echo "      <button class=\"btn tiny ghost\" type=\"button\" data-member-create-close>&times;</button>\n";
    echo "    </div>\n";
    echo "    <div class=\"modal-body\">\n";
    echo $createMemberFormContent;
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</div>\n";

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

        echo "<div class=\"modal\" id=\"member-edit-modal\" data-member-edit-modal data-member-edit-auto-open=\"" . h($autoOpenEditId) . "\" aria-hidden=\"true\" role=\"dialog\" aria-modal=\"true\">\n";
        echo "  <div class=\"modal-card member-view-modal-card msk-view-modal-card\">\n";
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

    if ($leftMembers > 0) {
        echo "<section class=\"card member-left-archive\">\n";
        echo "  <details>\n";
        echo "    <summary>Arsip Jemaat Sudah Keluar (" . h((string) $leftMembers) . ")</summary>\n";
        echo "    <div class=\"table-wrap member-left-archive-wrap\">\n";
        echo "      <table class=\"table\">\n";
        echo "        <thead><tr><th>Nama Lengkap</th><th>Tanggal Keluar</th><th>Alasan Keluar</th><th>WhatsApp</th><th class=\"actions-head\">Aksi</th></tr></thead>\n";
        echo "        <tbody>\n";
        foreach ($leftMembersSorted as $member) {
            $memberId = (string) ($member['id'] ?? '');
            $fullName = trim((string) ($member['full_name'] ?? '-'));
            if ($fullName === '') {
                $fullName = '-';
            }

            $leftDate = normalize_ymd_date((string) ($member['left_at'] ?? ''));
            $leftDateLabel = $leftDate !== '' ? format_indo_date($leftDate) : '-';
            $leftReason = trim((string) ($member['left_reason'] ?? ''));
            if ($leftReason === '') {
                $leftReason = '-';
            }

            $whatsapp = trim((string) ($member['whatsapp'] ?? ''));
            $waDisplay = $whatsapp !== '' ? $whatsapp : '-';
            $waDigits = preg_replace('/\\D+/', '', $whatsapp) ?? '';
            if ($waDigits !== '' && strpos($waDigits, '0') === 0) {
                $waDigits = '62' . substr($waDigits, 1);
            }
            $waHtml = h($waDisplay);
            if ($waDigits !== '') {
                $waHtml = "<a class=\"note-link\" href=\"" . h('https://wa.me/' . $waDigits) . "\" target=\"_blank\" rel=\"noopener\">" . h($waDisplay) . "</a>";
            }

            echo "<tr>";
            echo "<td>" . h($fullName) . "</td>";
            echo "<td>" . h($leftDateLabel) . "</td>";
            echo "<td>" . h($leftReason) . "</td>";
            echo "<td>" . $waHtml . "</td>";
            echo "<td class=\"actions\">";
            echo "<button class=\"btn tiny icon-btn\" type=\"button\" data-member-edit-open=\"" . h($memberId) . "\" aria-label=\"Edit\" title=\"Edit\">" . icon_svg('edit') . "</button>";
            echo "<form method=\"post\" class=\"inline\" onsubmit=\"return confirm('Aktifkan kembali jemaat ini?');\">";
            echo "<input type=\"hidden\" name=\"action\" value=\"mark_member_active\">";
            echo "<input type=\"hidden\" name=\"id\" value=\"" . h($memberId) . "\">";
            echo "<button class=\"btn tiny secondary icon-btn\" type=\"submit\" aria-label=\"Aktifkan\" title=\"Aktifkan\">" . icon_svg('check') . "</button>";
            echo "</form>";
            echo "</td>";
            echo "</tr>\n";
        }
        echo "        </tbody>\n";
        echo "      </table>\n";
        echo "    </div>\n";
        echo "  </details>\n";
        echo "</section>\n";
    }

    page_footer();
    legacy_exit();
}
