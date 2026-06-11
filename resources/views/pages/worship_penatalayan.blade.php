<?php

if ($page === 'worship_penatalayan') {
    page_header('Penatalayan Ibadah Umum', $settings, $page, false);

    render_condition_alerts([
        ['when' => isset($_GET['saved']), 'tone' => 'success', 'message' => 'Jadwal penatalayan berhasil disimpan.'],
        ['when' => isset($_GET['deleted']), 'tone' => 'success', 'message' => 'Jadwal penatalayan berhasil dihapus.'],
    ]);

    $error = trim((string) ($_GET['error'] ?? ''));
    render_mapped_error_alert($error, [
        'invalid_schedule' => 'Jadwal penatalayan yang dipilih tidak ditemukan.',
    ]);

    $selectedMonth = normalize_month_value((string) ($_GET['month'] ?? date('Y-m')));
    $schedulesByMonth = [];
    foreach ($worshipPenatalayanSchedules as $scheduleRow) {
        $scheduleMonth = normalize_month_value((string) ($scheduleRow['month'] ?? date('Y-m')));
        $schedulesByMonth[$scheduleMonth] = $scheduleRow;
    }

    $selectedExistingSchedule = $schedulesByMonth[$selectedMonth] ?? null;
    $selectedSchedule = build_worship_penatalayan_schedule($selectedMonth, $selectedExistingSchedule);
    $selectedWeekDates = is_array($selectedSchedule['week_dates'] ?? null) ? $selectedSchedule['week_dates'] : [];
    $serviceCounts = worship_penatalayan_service_counts($selectedSchedule);
    $historicalNames = worship_penatalayan_historical_service_names($worshipPenatalayanSchedules);
    $displayNameMap = [];
    foreach ($historicalNames as $historicalName) {
        $displayNameMap[$historicalName] = true;
    }
    foreach (array_keys($serviceCounts) as $serviceName) {
        $displayNameMap[(string) $serviceName] = true;
    }
    $displayStewardNames = array_keys($displayNameMap);
    usort($displayStewardNames, static function (string $a, string $b) use ($serviceCounts): int {
        $countCompare = ((int) ($serviceCounts[$b] ?? 0)) <=> ((int) ($serviceCounts[$a] ?? 0));
        if ($countCompare !== 0) {
            return $countCompare;
        }
        return strcasecmp($a, $b);
    });
    $historicalNamesJson = json_encode(array_values($historicalNames), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    if (!is_string($historicalNamesJson)) {
        $historicalNamesJson = '[]';
    }
    $totalStewardMonths = count($worshipPenatalayanSchedules);
    $lastUpdatedAt = trim((string) ($selectedSchedule['updated_at'] ?? ''));
    $lastUpdatedDateValue = normalize_ymd_date($lastUpdatedAt);
    $lastUpdatedStatLabel = $lastUpdatedDateValue !== '' ? (format_short_indo_date($lastUpdatedDateValue) . ' ' . substr($lastUpdatedDateValue, 0, 4)) : 'Belum ada';

    echo "<section class=\"card msk-hero-card worship-hero-card worship-steward-hero-card\">\n";
    echo "  <div class=\"msk-hero-head\">\n";
    echo "    <div class=\"msk-hero-copy\">\n";
    echo "      <span class=\"msk-hero-kicker\">Ibadah Umum</span>\n";
    echo "      <h1>Penatalayan Ibadah Umum</h1>\n";
    echo "      <p>Atur pembagian pelayanan setiap Minggu per bulan, isi nama pelayan langsung di tabel, lalu simpan jadwal penatalayan agar mudah dipakai saat koordinasi ibadah.</p>\n";
    echo "    </div>\n";
    echo "    <div class=\"msk-hero-stats\" aria-label=\"Ringkasan penatalayan ibadah umum\">\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Bulan Dipilih</span><strong class=\"msk-hero-stat-value\">" . h(format_indo_month($selectedMonth)) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Minggu Ibadah</span><strong class=\"msk-hero-stat-value\">" . h((string) count($selectedWeekDates)) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Update Terakhir</span><strong class=\"msk-hero-stat-value\">" . h($lastUpdatedStatLabel) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Bulan Tersimpan</span><strong class=\"msk-hero-stat-value\">" . h((string) $totalStewardMonths) . "</strong></div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <div class=\"actions table-tools msk-hero-tools worship-steward-hero-tools\">\n";
    echo "    <div class=\"msk-hero-controls worship-steward-hero-controls\">\n";
    echo "      <form method=\"get\" class=\"worship-steward-month-form\">\n";
    echo "        <input type=\"hidden\" name=\"page\" value=\"worship_penatalayan\">\n";
    echo "        <input type=\"month\" name=\"month\" value=\"" . h($selectedMonth) . "\" required aria-label=\"Pilih bulan jadwal penatalayan\" onchange=\"this.form.submit()\">\n";
    echo "      </form>\n";
    echo "      <div class=\"worship-steward-hero-fields\">\n";
    echo "        <label class=\"worship-steward-hero-field\">Judul Jadwal<input form=\"worship-steward-form\" type=\"text\" name=\"title\" value=\"" . h((string) ($selectedSchedule['title'] ?? '')) . "\" placeholder=\"" . h(default_worship_penatalayan_title($selectedMonth)) . "\"></label>\n";
    echo "        <label class=\"worship-steward-hero-field\">Catatan Update<input form=\"worship-steward-form\" type=\"text\" name=\"update_note\" value=\"" . h((string) ($selectedSchedule['update_note'] ?? '')) . "\" placeholder=\"Contoh: update 23 Feb\"></label>\n";
    echo "      </div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<section class=\"card worship-steward-editor-card\">\n";
    echo "  <div class=\"steward-history\" style=\"margin:0 0 12px 0;padding:10px 14px;background:#f8fafc;border-radius:6px;border:1px solid #e6eef8\">\n";
    echo "    <div style=\"font-weight:700;margin-bottom:6px;color:#0f172a\">Jumlah melayani di bulan ini</div>\n";
    echo "    <div data-steward-count-list style=\"display:flex;flex-wrap:wrap;gap:6px;align-items:center\">\n";
    foreach ($displayStewardNames as $hn) {
        $count = isset($serviceCounts[$hn]) ? (int) $serviceCounts[$hn] : 0;
        echo "      <span class=\"worship-steward-count-chip\" data-steward-name=\"" . h($hn) . "\" style=\"display:inline-block;padding:6px 10px;border-radius:999px;background:#eef2ff;color:#0f172a;font-size:13px\">" . h($hn) . " (" . h((string) $count) . ")</span>\n";
    }
    if (count($displayStewardNames) === 0) {
        echo "      <span data-steward-count-empty style=\"color:#64748b;font-size:13px\">Belum ada riwayat penatalayan.</span>\n";
    }
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <form method=\"post\" id=\"worship-steward-form\" class=\"worship-steward-form\" autocomplete=\"off\">\n";
    echo "    <input type=\"hidden\" name=\"action\" value=\"save_worship_penatalayan\">\n";
    echo "    <input type=\"hidden\" name=\"month\" value=\"" . h($selectedMonth) . "\">\n";
    echo "    <div class=\"table-wrap worship-steward-table-wrap\">\n";
    echo "      <table class=\"table worship-steward-planner-table\">\n";
    echo "        <thead><tr><th>Pelayanan</th>";
    foreach ($selectedWeekDates as $weekDate) {
        echo "<th>" . h(format_short_indo_weekday_date((string) $weekDate)) . "</th>";
    }
    echo "</tr></thead>\n";
    echo "        <tbody>\n";
    foreach (($selectedSchedule['rows'] ?? []) as $rowIndex => $scheduleRow) {
        $roleLabel = trim((string) ($scheduleRow['role'] ?? '-'));
        if ($roleLabel === '') {
            $roleLabel = '-';
        }
        $assignments = is_array($scheduleRow['assignments'] ?? null) ? $scheduleRow['assignments'] : [];
        $roleKey = strtolower($roleLabel);
        $isDualRole = in_array($roleKey, ['singer', 'keyboard'], true);
        $isTrainingSchedule = $roleKey === 'jadwal latihan';
        echo "<tr>";
        echo "<th><input type=\"hidden\" name=\"row_labels[]\" value=\"" . h($roleLabel) . "\"><span class=\"worship-steward-row-label\">" . h($roleLabel) . "</span></th>";
        for ($weekIndex = 0; $weekIndex < count($selectedWeekDates); $weekIndex++) {
            $cellValue = (string) ($assignments[$weekIndex] ?? '');
            if ($isTrainingSchedule) {
                $trainingValue = worship_penatalayan_training_field_value($cellValue, $selectedMonth);
                $trainingLabel = $trainingValue !== '' ? format_short_indo_weekday_date($trainingValue) : 'Pilih tanggal latihan';
                echo "<td><div class=\"worship-steward-cell-shell worship-steward-training-field\">";
                echo "<input class=\"worship-steward-training-input\" type=\"date\" name=\"assignments[" . (string) $rowIndex . "][" . (string) $weekIndex . "]\" value=\"" . h($trainingValue) . "\">";
                echo "<span class=\"worship-steward-training-preview\" data-empty=\"Pilih tanggal latihan\">" . h($trainingLabel) . "</span>";
                echo "</div></td>";
                continue;
            }
            if ($isDualRole) {
                $cellLines = preg_split("/\r\n?|\n/", $cellValue) ?: [];
                $firstValue = trim((string) ($cellLines[0] ?? ''));
                $secondValue = trim((string) ($cellLines[1] ?? ''));
                echo "<td><div class=\"worship-steward-cell-shell worship-steward-duo\">";
                echo "<input autocomplete=\"off\" class=\"worship-steward-duo-input\" data-steward-count-field=\"1\" type=\"text\" name=\"assignments[" . (string) $rowIndex . "][" . (string) $weekIndex . "][]\" value=\"" . h($firstValue) . "\" placeholder=\"Nama 1\">";
                echo "<input autocomplete=\"off\" class=\"worship-steward-duo-input\" data-steward-count-field=\"1\" type=\"text\" name=\"assignments[" . (string) $rowIndex . "][" . (string) $weekIndex . "][]\" value=\"" . h($secondValue) . "\" placeholder=\"Nama 2\">";
                echo "</div></td>";
                continue;
            }
            echo "<td><div class=\"worship-steward-cell-shell\"><textarea autocomplete=\"off\" class=\"worship-steward-cell\" data-steward-count-field=\"1\" name=\"assignments[" . (string) $rowIndex . "][" . (string) $weekIndex . "]\" rows=\"1\" placeholder=\"Nama\">" . h($cellValue) . "</textarea></div></td>";
        }
        echo "</tr>\n";
    }
    echo "        </tbody>\n";
    echo "      </table>\n";
    echo "    </div>\n";
    echo "  </form>\n";
    echo "  <div class=\"worship-steward-action-bar\">\n";
    echo "    <button class=\"btn\" form=\"worship-steward-form\" type=\"submit\">Simpan Jadwal</button>\n";
    if ($selectedExistingSchedule !== null) {
        echo "  <form method=\"post\" class=\"worship-steward-danger-form\" onsubmit=\"return confirm('Hapus jadwal penatalayan untuk bulan ini?');\">\n";
        echo "    <input type=\"hidden\" name=\"action\" value=\"delete_worship_penatalayan\">\n";
        echo "    <input type=\"hidden\" name=\"month\" value=\"" . h($selectedMonth) . "\">\n";
        echo "    <button class=\"btn ghost\" type=\"submit\">Hapus Jadwal Bulan Ini</button>\n";
        echo "  </form>\n";
    }
    echo "  </div>\n";
    echo "</section>\n";

    echo "<section class=\"card table-card-plain\">\n";
    echo "  <div class=\"card-row\">\n";
    echo "    <h2>Arsip Jadwal Bulanan</h2>\n";
    echo "  </div>\n";
    echo "  <div class=\"table-wrap\">\n";
    echo "    <table class=\"table\">\n";
    echo "      <thead><tr><th>Bulan</th><th>Judul</th><th>Catatan Update</th><th>Jumlah Minggu</th><th>Terakhir Disimpan</th><th class=\"actions-head\">Aksi</th></tr></thead>\n";
    echo "      <tbody>\n";
    foreach ($worshipPenatalayanSchedules as $savedSchedule) {
        $scheduleMonth = normalize_month_value((string) ($savedSchedule['month'] ?? date('Y-m')));
        $scheduleTitle = trim((string) ($savedSchedule['title'] ?? default_worship_penatalayan_title($scheduleMonth)));
        if ($scheduleTitle === '') {
            $scheduleTitle = default_worship_penatalayan_title($scheduleMonth);
        }
        $scheduleNote = trim((string) ($savedSchedule['update_note'] ?? ''));
        $updatedAtLabel = format_datetime_id((string) ($savedSchedule['updated_at'] ?? ''));
        $rowClass = $scheduleMonth === $selectedMonth ? ' class="row-highlight"' : '';
        echo "<tr" . $rowClass . ">";
        echo "<td>" . h(format_indo_month($scheduleMonth)) . "</td>";
        echo "<td><div class=\"worship-steward-saved-title\"><strong>" . h($scheduleTitle) . "</strong><span>" . h(default_worship_penatalayan_title($scheduleMonth)) . "</span></div></td>";
        echo "<td>" . h($scheduleNote !== '' ? $scheduleNote : '-') . "</td>";
        echo "<td>" . h((string) count(worship_penatalayan_week_dates($scheduleMonth))) . "</td>";
        echo "<td>" . h($updatedAtLabel) . "</td>";
        echo "<td class=\"actions\">";
        echo "<a class=\"btn tiny secondary icon-btn\" href=\"?page=worship_penatalayan&month=" . rawurlencode($scheduleMonth) . "\" aria-label=\"Buka\" title=\"Buka\">" . icon_svg('eye') . "</a>";
        echo "<a class=\"btn tiny ghost icon-btn\" href=\"?page=worship_penatalayan_image&month=" . rawurlencode($scheduleMonth) . "\" aria-label=\"Cetak\" title=\"Cetak\">" . icon_svg('print') . "</a>";
        echo "<form method=\"post\" class=\"inline\" onsubmit=\"return confirm('Hapus jadwal penatalayan bulan ini?');\">";
        echo "<input type=\"hidden\" name=\"action\" value=\"delete_worship_penatalayan\">";
        echo "<input type=\"hidden\" name=\"month\" value=\"" . h($scheduleMonth) . "\">";
        echo "<button class=\"btn tiny danger icon-btn\" type=\"submit\" aria-label=\"Hapus\" title=\"Hapus\">" . icon_svg('trash') . "</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>\n";
    }
    if (count($worshipPenatalayanSchedules) === 0) {
        echo "<tr><td colspan=\"6\">Belum ada jadwal penatalayan yang disimpan. Pilih bulan, isi tabel pelayanan, lalu simpan jadwal pertama.</td></tr>\n";
    }
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<script>\n";
    echo "(function () {\n";
    echo "  var stewardKnownNames = " . $historicalNamesJson . ";\n";
    echo "  var stewardCountList = document.querySelector('[data-steward-count-list]');\n";
    echo "  var stewardChipStyle = 'display:inline-block;padding:6px 10px;border-radius:999px;background:#eef2ff;color:#0f172a;font-size:13px';\n";
    echo "  var splitStewardNames = function (value) {\n";
    echo "    var result = [];\n";
    echo "    String(value || '').split(/\\r\\n?|\\n/).forEach(function (part) {\n";
    echo "      var name = String(part || '').trim();\n";
    echo "      if (name !== '') {\n";
    echo "        result.push(name);\n";
    echo "      }\n";
    echo "    });\n";
    echo "    return result;\n";
    echo "  };\n";
    echo "  var compareStewardNames = function (a, b) {\n";
    echo "    try {\n";
    echo "      return String(a).localeCompare(String(b), 'id', { sensitivity: 'base' });\n";
    echo "    } catch (error) {\n";
    echo "      var lowerA = String(a).toLowerCase();\n";
    echo "      var lowerB = String(b).toLowerCase();\n";
    echo "      if (lowerA === lowerB) {\n";
    echo "        return 0;\n";
    echo "      }\n";
    echo "      return lowerA < lowerB ? -1 : 1;\n";
    echo "    }\n";
    echo "  };\n";
    echo "  var renderStewardCounts = function () {\n";
    echo "    if (!stewardCountList) {\n";
    echo "      return;\n";
    echo "    }\n";
    echo "    var counts = {};\n";
    echo "    var nameMap = {};\n";
    echo "    if (Array.isArray(stewardKnownNames)) {\n";
    echo "      stewardKnownNames.forEach(function (name) {\n";
    echo "        var label = String(name || '').trim();\n";
    echo "        if (label !== '') {\n";
    echo "          nameMap[label] = true;\n";
    echo "        }\n";
    echo "      });\n";
    echo "    }\n";
    echo "    document.querySelectorAll('[data-steward-count-field=\"1\"]').forEach(function (field) {\n";
    echo "      splitStewardNames(field.value).forEach(function (name) {\n";
    echo "        counts[name] = (counts[name] || 0) + 1;\n";
    echo "        nameMap[name] = true;\n";
    echo "      });\n";
    echo "    });\n";
    echo "    var names = Object.keys(nameMap);\n";
    echo "    names.sort(function (a, b) {\n";
    echo "      var countDiff = (counts[b] || 0) - (counts[a] || 0);\n";
    echo "      if (countDiff !== 0) {\n";
    echo "        return countDiff;\n";
    echo "      }\n";
    echo "      return compareStewardNames(a, b);\n";
    echo "    });\n";
    echo "    stewardCountList.innerHTML = '';\n";
    echo "    if (names.length === 0) {\n";
    echo "      var empty = document.createElement('span');\n";
    echo "      empty.setAttribute('data-steward-count-empty', '1');\n";
    echo "      empty.style.cssText = 'color:#64748b;font-size:13px';\n";
    echo "      empty.textContent = 'Belum ada riwayat penatalayan.';\n";
    echo "      stewardCountList.appendChild(empty);\n";
    echo "      return;\n";
    echo "    }\n";
    echo "    names.forEach(function (name) {\n";
    echo "      var chip = document.createElement('span');\n";
    echo "      chip.className = 'worship-steward-count-chip';\n";
    echo "      chip.setAttribute('data-steward-name', name);\n";
    echo "      chip.style.cssText = stewardChipStyle;\n";
    echo "      chip.textContent = name + ' (' + String(counts[name] || 0) + ')';\n";
    echo "      stewardCountList.appendChild(chip);\n";
    echo "    });\n";
    echo "  };\n";
    echo "  document.querySelectorAll('[data-steward-count-field=\"1\"]').forEach(function (field) {\n";
    echo "    field.addEventListener('input', renderStewardCounts);\n";
    echo "    field.addEventListener('change', renderStewardCounts);\n";
    echo "  });\n";
    echo "  renderStewardCounts();\n";
    echo "  var fields = document.querySelectorAll('textarea.worship-steward-cell');\n";
    echo "  var resizeField = function (field) {\n";
    echo "    if (!field) {\n";
    echo "      return;\n";
    echo "    }\n";
    echo "    field.style.height = 'auto';\n";
    echo "    field.style.height = field.scrollHeight + 'px';\n";
    echo "  };\n";
    echo "  if (fields && fields.length > 0) {\n";
    echo "    fields.forEach(function (field) {\n";
    echo "      resizeField(field);\n";
    echo "      field.addEventListener('input', function () {\n";
    echo "        resizeField(field);\n";
    echo "      });\n";
    echo "    });\n";
    echo "  }\n";
    echo "  var trainingInputs = document.querySelectorAll('.worship-steward-training-input');\n";
    echo "  var formatTrainingDate = function (value) {\n";
    echo "    if (!value) {\n";
    echo "      return '';\n";
    echo "    }\n";
    echo "    var parts = value.split('-');\n";
    echo "    if (parts.length !== 3) {\n";
    echo "      return value;\n";
    echo "    }\n";
    echo "    var year = parseInt(parts[0], 10);\n";
    echo "    var month = parseInt(parts[1], 10);\n";
    echo "    var day = parseInt(parts[2], 10);\n";
    echo "    if (!year || !month || !day) {\n";
    echo "      return value;\n";
    echo "    }\n";
    echo "    var date = new Date(Date.UTC(year, month - 1, day));\n";
    echo "    if (Number.isNaN(date.getTime())) {\n";
    echo "      return value;\n";
    echo "    }\n";
    echo "    try {\n";
    echo "      return new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(date);\n";
    echo "    } catch (error) {\n";
    echo "      return value;\n";
    echo "    }\n";
    echo "  };\n";
    echo "  var updateTrainingPreview = function (input) {\n";
    echo "    if (!input) {\n";
    echo "      return;\n";
    echo "    }\n";
    echo "    var field = input.closest('.worship-steward-training-field');\n";
    echo "    if (!field) {\n";
    echo "      return;\n";
    echo "    }\n";
    echo "    var preview = field.querySelector('.worship-steward-training-preview');\n";
    echo "    if (!preview) {\n";
    echo "      return;\n";
    echo "    }\n";
    echo "    var formatted = formatTrainingDate(input.value);\n";
    echo "    preview.textContent = formatted || preview.getAttribute('data-empty') || '';\n";
    echo "  };\n";
    echo "  if (trainingInputs && trainingInputs.length > 0) {\n";
    echo "    trainingInputs.forEach(function (input) {\n";
    echo "      updateTrainingPreview(input);\n";
    echo "      input.addEventListener('input', function () {\n";
    echo "        updateTrainingPreview(input);\n";
    echo "      });\n";
    echo "      input.addEventListener('change', function () {\n";
    echo "        updateTrainingPreview(input);\n";
    echo "      });\n";
    echo "    });\n";
    echo "  }\n";
    echo "})();\n";
    echo "</script>\n";

    page_footer();
    legacy_exit();
}
