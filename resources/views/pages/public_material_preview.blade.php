<?php

if ($page === 'public_material_preview') {
    $menu = normalize_public_material_menu((string) ($_GET['menu'] ?? ''));
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($menu === '' || $id === '') {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }

    $allowedRows = church_files_for_public_material($churchFiles, $menu);
    $selected = null;
    foreach ($allowedRows as $row) {
        if ((string) ($row['id'] ?? '') === $id) {
            $selected = $row;
            break;
        }
    }
    if ($selected === null) {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }

    $path = sanitize_relative_upload_path((string) ($selected['path'] ?? ''));
    if ($path === '' || !is_upload_path($path)) {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }
    if (!is_public_material_previewable_path($path)) {
        redirect_to('public_material_download', ['menu' => $menu, 'id' => $id]);
    }

    $fullPath = legacy_runtime_path($path);
    if (!is_file($fullPath)) {
        http_response_code(404);
        legacy_exit('File tidak ditemukan.');
    }

    $ext = secure_file_extension($path);
    $fileName = trim((string) ($selected['file_name'] ?? basename($path)));
    if ($fileName === '') {
        $fileName = basename($path);
    }
    $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', $fileName) ?? $fileName;
    if ($downloadName === '') {
        $downloadName = 'materi';
    }
    $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'materi';
    if ($asciiDownloadName === '') {
        $asciiDownloadName = 'materi';
    }
    $isRawPreview = trim((string) ($_GET['raw'] ?? '')) === '1';
    $showDgJournalButton = is_public_material_dg_session_menu($menu);
    $showFeedbackButton = is_public_material_feedback_session($menu, $selected);

    if ($ext === 'pdf' && !$isRawPreview && $showDgJournalButton) {
        $previewTitle = trim((string) ($selected['title'] ?? ''));
        if ($previewTitle === '') {
            $previewTitle = $downloadName;
        }
        if ($previewTitle === '') {
            $previewTitle = 'Materi DG';
        }
        $rawUrl = '?' . http_build_query([
            'page' => 'public_material_preview',
            'menu' => $menu,
            'id' => $id,
            'raw' => '1',
        ]);

        page_header_plain($previewTitle, $settings, 'page-file-preview-standalone page-public-material-preview');
        echo "<section class=\"public-material-preview-shell\" aria-label=\"Preview materi DG\" data-public-material-pdf-viewer data-pdf-url=\"" . h($rawUrl) . "\">\n";
        echo "  <div class=\"public-material-native-pdf\" data-native-pdf>\n";
        echo "    <iframe class=\"file-page-embed public-material-preview-embed\" src=\"" . h($rawUrl) . "\" loading=\"eager\" referrerpolicy=\"same-origin\" title=\"" . h($previewTitle) . "\"></iframe>\n";
        echo "  </div>\n";
        echo "  <div class=\"public-material-pdfjs-viewer\" data-pdfjs-viewer hidden>\n";
        echo "    <div class=\"public-material-pdfjs-status\" data-pdfjs-status>Memuat PDF...</div>\n";
        echo "    <div class=\"public-material-pdfjs-pages\" data-pdfjs-pages></div>\n";
        echo "    <div class=\"public-material-pdfjs-fallback\" data-pdfjs-fallback hidden>PDF belum bisa ditampilkan di browser ini. <a href=\"" . h($rawUrl) . "\">Buka PDF</a></div>\n";
        echo "  </div>\n";
        echo "  <div class=\"public-material-preview-actions\">\n";
        echo "    <a class=\"btn public-material-journal-btn\" href=\"?page=public_dg_branch\">Isi Jurnal Temu DG</a>\n";
        if ($showFeedbackButton) {
            $feedbackSessionNumber = public_material_session_number($selected);
            $feedbackHref = '?page=public_member_feedback_branch';
            if (in_array($feedbackSessionNumber, [3, 12], true)) {
                $feedbackHref .= '&amp;feedback_session=' . h((string) $feedbackSessionNumber);
            }
            echo "    <a class=\"btn secondary public-material-feedback-btn\" href=\"" . $feedbackHref . "\">Isi Jurnal Umpan Balik Anggota</a>\n";
        }
        echo "  </div>\n";
        echo "</section>\n";
        echo "<script>\n";
        echo "(function () {\n";
        echo "  var shell = document.querySelector('[data-public-material-pdf-viewer]');\n";
        echo "  var userAgent = navigator.userAgent || '';\n";
        echo "  var isIos = /iPad|iPhone|iPod/i.test(userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);\n";
        echo "  if (!shell || !(/Android/i.test(userAgent) || isIos)) { return; }\n";
        echo "  var pdfUrl = shell.getAttribute('data-pdf-url') || '';\n";
        echo "  var nativePdf = shell.querySelector('[data-native-pdf]');\n";
        echo "  var viewer = shell.querySelector('[data-pdfjs-viewer]');\n";
        echo "  var status = shell.querySelector('[data-pdfjs-status]');\n";
        echo "  var pages = shell.querySelector('[data-pdfjs-pages]');\n";
        echo "  var fallback = shell.querySelector('[data-pdfjs-fallback]');\n";
        echo "  if (!pdfUrl || !viewer || !pages) { return; }\n";
        echo "  if (nativePdf) { nativePdf.hidden = true; }\n";
        echo "  viewer.hidden = false;\n";
        echo "  function showError() {\n";
        echo "    if (status) { status.hidden = true; }\n";
        echo "    if (fallback) { fallback.hidden = false; }\n";
        echo "  }\n";
        echo "  function loadScript(src, done) {\n";
        echo "    var script = document.createElement('script');\n";
        echo "    script.src = src;\n";
        echo "    script.async = true;\n";
        echo "    script.onload = function () { done(null); };\n";
        echo "    script.onerror = function () { done(new Error('pdfjs-load-failed')); };\n";
        echo "    document.head.appendChild(script);\n";
        echo "  }\n";
        echo "  function renderPage(pdf, pageNumber) {\n";
        echo "    return pdf.getPage(pageNumber).then(function (page) {\n";
        echo "      var baseViewport = page.getViewport({ scale: 1 });\n";
        echo "      var availableWidth = Math.max(240, pages.clientWidth - 16);\n";
        echo "      var scale = Math.min(2, Math.max(0.7, availableWidth / baseViewport.width));\n";
        echo "      var viewport = page.getViewport({ scale: scale });\n";
        echo "      var ratio = Math.min(window.devicePixelRatio || 1, 2);\n";
        echo "      var canvas = document.createElement('canvas');\n";
        echo "      var context = canvas.getContext('2d');\n";
        echo "      canvas.className = 'public-material-pdfjs-page';\n";
        echo "      canvas.width = Math.floor(viewport.width * ratio);\n";
        echo "      canvas.height = Math.floor(viewport.height * ratio);\n";
        echo "      canvas.style.width = Math.floor(viewport.width) + 'px';\n";
        echo "      canvas.style.height = Math.floor(viewport.height) + 'px';\n";
        echo "      context.setTransform(ratio, 0, 0, ratio, 0, 0);\n";
        echo "      pages.appendChild(canvas);\n";
        echo "      return page.render({ canvasContext: context, viewport: viewport }).promise;\n";
        echo "    });\n";
        echo "  }\n";
        echo "  loadScript('assets/vendor/pdfjs/pdf.min.js', function (error) {\n";
        echo "    if (error || !window.pdfjsLib) { showError(); return; }\n";
        echo "    window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/vendor/pdfjs/pdf.worker.min.js';\n";
        echo "    fetch(pdfUrl, { credentials: 'same-origin' }).then(function (response) {\n";
        echo "      if (!response.ok) { throw new Error('pdf-fetch-failed'); }\n";
        echo "      return response.arrayBuffer();\n";
        echo "    }).then(function (buffer) {\n";
        echo "      return window.pdfjsLib.getDocument({ data: buffer }).promise;\n";
        echo "    }).then(function (pdf) {\n";
        echo "      if (status) { status.textContent = 'Memuat ' + pdf.numPages + ' halaman...'; }\n";
        echo "      var sequence = Promise.resolve();\n";
        echo "      for (var pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {\n";
        echo "        (function (number) { sequence = sequence.then(function () { return renderPage(pdf, number); }); })(pageNumber);\n";
        echo "      }\n";
        echo "      sequence.then(function () { if (status) { status.hidden = true; } }).catch(showError);\n";
        echo "    }).catch(showError);\n";
        echo "  });\n";
        echo "}());\n";
        echo "</script>\n";
        page_footer_plain();
        legacy_exit();
    }

    $contentType = secure_file_mime_by_extension($ext);
    if ($contentType === '') {
        $contentType = detect_file_mime_type($fullPath);
    }
    if ($contentType === '') {
        $contentType = 'application/octet-stream';
    }
    $contentLength = (int) @filesize($fullPath);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header('Content-Type: ' . $contentType);
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Download-Options: noopen');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Disposition: inline; filename="' . $asciiDownloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    if ($contentLength > 0) {
        header('Content-Length: ' . (string) $contentLength);
    }

    $fp = fopen($fullPath, 'rb');
    if ($fp === false) {
        http_response_code(500);
        legacy_exit('Gagal membaca file.');
    }
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false) {
            break;
        }
        echo $chunk;
    }
    fclose($fp);
    legacy_exit();
}
