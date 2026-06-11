<?php

function render_member_form_html(array $member, string $closeActionAttr = '', string $returnPage = 'members', string $returnMissing = 'all'): string {
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

    if (!in_array($returnPage, ['members', 'member_completeness'], true)) {
        $returnPage = 'members';
    }
    if ($returnPage !== 'member_completeness') {
        $returnMissing = '';
    } else {
        $allowedReturnMissing = member_completeness_filter_options();
        if (!isset($allowedReturnMissing[$returnMissing])) {
            $returnMissing = 'all';
        }
    }

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
    echo "  <input type=\"hidden\" name=\"return_page\" value=\"" . h($returnPage) . "\">\n";
    if ($returnPage === 'member_completeness') {
        echo "  <input type=\"hidden\" name=\"return_missing\" value=\"" . h($returnMissing) . "\">\n";
    }
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
    if ($closeActionAttr !== '') {
        echo "    <button class=\"btn ghost\" type=\"button\" " . $closeActionAttr . ">Batal</button>\n";
    }
    echo "  </div>\n";
    echo "</form>\n";

    return (string) ob_get_clean();
}
