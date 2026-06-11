<?php

function render_member_view_html(array $member, array $membersById): string {
    $fullName = trim((string) ($member['full_name'] ?? ''));
    if ($fullName === '') {
        $fullName = '-';
    }

    $detailGender = normalize_member_gender_value((string) ($member['gender'] ?? ''));
    if ($detailGender === '') {
        $detailGender = '-';
    }

    $detailBirthPlace = trim((string) ($member['birth_place'] ?? ''));
    if ($detailBirthPlace === '') {
        $detailBirthPlace = '-';
    }

    $birthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
    $birthDayMonth = member_birth_day_month($member);
    $detailBirthDateLabel = '-';
    if ($birthDate !== '') {
        $detailBirthDateLabel = format_indo_date($birthDate);
    } elseif ($birthDayMonth !== '') {
        $detailBirthDateLabel = format_member_birth_day_month($birthDayMonth) . ' (tanpa tahun)';
    }

    $detailAddress = trim((string) ($member['address'] ?? ''));
    if ($detailAddress === '') {
        $detailAddress = '-';
    }

    $detailEmail = strtolower(trim((string) ($member['email'] ?? '')));
    if ($detailEmail !== '' && filter_var($detailEmail, FILTER_VALIDATE_EMAIL) === false) {
        $detailEmail = '';
    }
    $detailEmailHtml = '-';
    if ($detailEmail !== '') {
        $detailEmailHtml = "<a class=\"note-link\" href=\"" . h('mailto:' . $detailEmail) . "\">" . h($detailEmail) . "</a>";
    }

    $whatsapp = trim((string) ($member['whatsapp'] ?? ''));
    $waDisplay = $whatsapp !== '' ? $whatsapp : '-';
    $waDigits = normalize_whatsapp_digits($whatsapp);
    $detailWaHtml = h($waDisplay);
    if ($waDigits !== '') {
        $detailWaHtml = "<a class=\"note-link\" href=\"" . h('https://wa.me/' . $waDigits) . "\" target=\"_blank\" rel=\"noopener\">" . h($waDisplay) . "</a>";
    }

    $detailSocialMedia = trim((string) ($member['social_media'] ?? ''));
    $detailSocialMediaHtml = '-';
    if ($detailSocialMedia !== '') {
        $detailSocialMediaHtml = "<a class=\"note-link\" href=\"" . h($detailSocialMedia) . "\" target=\"_blank\" rel=\"noopener\">Buka Link</a>";
    }

    $detailFamilyIds = $member['family_ids'] ?? [];
    if (!is_array($detailFamilyIds)) {
        $detailFamilyIds = [];
    }
    $detailFamilyNames = [];
    foreach ($detailFamilyIds as $familyId) {
        $familyId = trim((string) $familyId);
        if ($familyId === '' || !isset($membersById[$familyId])) {
            continue;
        }
        $familyName = trim((string) ($membersById[$familyId]['full_name'] ?? ''));
        if ($familyName !== '') {
            $detailFamilyNames[] = $familyName;
        }
    }
    $detailFamilyLabel = count($detailFamilyNames) > 0 ? implode(', ', $detailFamilyNames) : '-';

    $detailPhotoLinks = [];
    $detailPhotoNumber = 0;
    foreach (extract_member_photos($member) as $photo) {
        $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
        if ($photoPath === '') {
            continue;
        }
        $photoUrl = secure_upload_url($photoPath);
        if ($photoUrl === '') {
            continue;
        }
        $detailPhotoNumber++;
        $photoLabel = 'Foto ' . (string) $detailPhotoNumber;
        $detailPhotoLinks[] = "<button class=\"note-link member-photo-preview-trigger\" type=\"button\" data-file-preview-open=\"image\" data-file-preview-fullres=\"1\" data-file-path=\"" . h($photoUrl) . "\" data-file-title=\"" . h($photoLabel) . "\">" . h($photoLabel) . "</button>";
    }
    $detailPhotoHtml = count($detailPhotoLinks) > 0
        ? "<div class=\"member-photo-links\">" . implode(' ', $detailPhotoLinks) . "</div>"
        : '-';

    ob_start();
    echo "<div class=\"table-wrap member-view-table-wrap\">";
    echo "<table class=\"table member-view-table\"><tbody>";
    echo "<tr><th>Nama Jemaat</th><td>" . h($fullName) . "</td><th>Jenis Kelamin</th><td>" . h($detailGender) . "</td></tr>";
    echo "<tr><th>Tempat Lahir</th><td>" . h($detailBirthPlace) . "</td><th>Tanggal Lahir</th><td>" . h($detailBirthDateLabel) . "</td></tr>";
    echo "<tr><th>Alamat</th><td>" . h($detailAddress) . "</td><th>Email</th><td>" . $detailEmailHtml . "</td></tr>";
    echo "<tr><th>WhatsApp</th><td>" . $detailWaHtml . "</td><th>Sosial Media</th><td>" . $detailSocialMediaHtml . "</td></tr>";
    echo "<tr><th>Keluarga</th><td colspan=\"3\">" . h($detailFamilyLabel) . "</td></tr>";
    echo "<tr><th>Foto</th><td colspan=\"3\">" . $detailPhotoHtml . "</td></tr>";
    echo "</tbody></table>";
    echo "</div>";

    return (string) ob_get_clean();
}
