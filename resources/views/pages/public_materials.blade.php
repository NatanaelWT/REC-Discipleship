<?php

if ($page === 'public_materials') {
    $menu = normalize_public_material_menu((string) ($_GET['menu'] ?? ''));
    if ($menu === '') {
        redirect_to('kutisari');
    }
    $menuOption = public_material_option($menu);
    $menuLabel = trim((string) ($menuOption['label'] ?? 'Materi'));
    $menuSubtitle = trim((string) ($menuOption['subtitle'] ?? 'Daftar file materi yang bisa diunduh.'));
    $menuFolder = normalize_church_folder_path((string) ($menuOption['folder'] ?? ''));
    $materialRows = church_files_for_public_material($churchFiles, $menu);

    page_header_plain($menuLabel, $settings, 'page-dg-public');
    echo "<section class=\"card public-material-card\">\n";
    echo "  <div class=\"card-row public-material-head\">\n";
    echo "    <div>\n";
    echo "      <h2>" . h($menuLabel) . "</h2>\n";
    echo "      <p class=\"public-material-subtitle\">" . h($menuSubtitle) . "</p>\n";
    echo "    </div>\n";
    echo "    <span class=\"public-material-count\">" . h((string) count($materialRows)) . " file</span>\n";
    echo "  </div>\n";

    if (count($materialRows) === 0) {
        $folderHint = $menuFolder === '' ? '-' : ('uploads/files/' . $menuFolder);
    } else {
        echo "  <div class=\"public-material-list\">\n";
        foreach ($materialRows as $row) {
            $fileId = trim((string) ($row['id'] ?? ''));
            if ($fileId === '') {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = trim((string) ($row['file_name'] ?? 'Dokumen'));
            }
            if ($title === '') {
                $title = 'Dokumen';
            }
            $path = sanitize_relative_upload_path((string) ($row['path'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $sizeLabel = format_file_size(max(0, (int) ($row['size'] ?? 0)));
            $ext = secure_file_extension($path);
            $extLabel = strtoupper($ext !== '' ? $ext : 'FILE');
            $isPreviewable = is_public_material_previewable_path($path);
            $downloadHref = '?page=public_material_download&menu=' . rawurlencode($menu) . '&id=' . rawurlencode($fileId);
            $previewHref = '?page=public_material_preview&menu=' . rawurlencode($menu) . '&id=' . rawurlencode($fileId);

            echo "    <article class=\"public-material-item\">\n";
            echo "      <div class=\"public-material-top\">\n";
            echo "        <div class=\"public-material-title\">" . h($title) . "</div>\n";
            echo "        <span class=\"public-material-ext\">" . h($extLabel) . "</span>\n";
            echo "      </div>\n";
            if ($description !== '') {
                echo "      <div class=\"public-material-desc\">" . h($description) . "</div>\n";
            }
            echo "      <div class=\"public-material-meta\">" . h($sizeLabel) . "</div>\n";
            echo "      <div class=\"public-material-actions\">\n";
            if ($isPreviewable) {
                echo "        <a class=\"btn tiny ghost\" href=\"" . h($previewHref) . "\" target=\"_blank\" rel=\"noopener\">Lihat</a>\n";
            }
            echo "        <a class=\"btn tiny secondary\" href=\"" . h($downloadHref) . "\">Unduh</a>\n";
            echo "      </div>\n";
            echo "    </article>\n";
        }
        echo "  </div>\n";
    }

    echo "  <div class=\"form-actions public-material-footer\">\n";
    echo "    <a class=\"btn ghost\" href=\"index.php\">Kembali</a>\n";
    echo "  </div>\n";
    echo "</section>\n";
    page_footer_plain();
    legacy_exit();
}
