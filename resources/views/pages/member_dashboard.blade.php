<?php

if ($page === 'member_dashboard') {
    page_header('Dashboard Data Jemaat', $settings, $page, false);

    $membersSorted = $members;
    usort($membersSorted, function ($a, $b) {
        return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
    });

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

    $totalMembers = count($activeMembersSorted);
    $leftMembers = count($leftMembersSorted);
    $activeMemberRows = filter_active_members($members);
    $familyGroups = member_family_groups($activeMemberRows);
    $totalFamilies = count($familyGroups);

    $genderChartRows = [
        ['label' => 'Laki-laki', 'value' => 0, 'color' => '#0f766e'],
        ['label' => 'Perempuan', 'value' => 0, 'color' => '#f59e0b'],
        ['label' => 'Belum Diisi', 'value' => 0, 'color' => '#94a3b8'],
    ];
    foreach ($activeMembersSorted as $member) {
        $gender = normalize_member_gender_value((string) ($member['gender'] ?? ''));
        if ($gender === 'Laki-laki') {
            $genderChartRows[0]['value']++;
        } elseif ($gender === 'Perempuan') {
            $genderChartRows[1]['value']++;
        } else {
            $genderChartRows[2]['value']++;
        }
    }

    $ageChartRows = [
        ['label' => '0-12', 'value' => 0, 'color' => '#0ea5e9'],
        ['label' => '13-17', 'value' => 0, 'color' => '#22c55e'],
        ['label' => '18-35', 'value' => 0, 'color' => '#14b8a6'],
        ['label' => '36-59', 'value' => 0, 'color' => '#f59e0b'],
        ['label' => '60+', 'value' => 0, 'color' => '#dc2626'],
        ['label' => 'Tidak Diketahui', 'value' => 0, 'color' => '#94a3b8'],
    ];
    $todayTimestamp = strtotime(today_date());
    foreach ($activeMembersSorted as $member) {
        $birthDate = normalize_ymd_date((string) ($member['birth_date'] ?? ''));
        if ($birthDate === '') {
            $ageChartRows[5]['value']++;
            continue;
        }
        $birthTimestamp = strtotime($birthDate);
        if ($birthTimestamp === false || $todayTimestamp === false || $birthTimestamp > $todayTimestamp) {
            $ageChartRows[5]['value']++;
            continue;
        }
        $age = (int) date('Y', $todayTimestamp) - (int) date('Y', $birthTimestamp);
        if ((int) date('md', $todayTimestamp) < (int) date('md', $birthTimestamp)) {
            $age--;
        }
        if ($age < 0) {
            $ageChartRows[5]['value']++;
        } elseif ($age <= 12) {
            $ageChartRows[0]['value']++;
        } elseif ($age <= 17) {
            $ageChartRows[1]['value']++;
        } elseif ($age <= 35) {
            $ageChartRows[2]['value']++;
        } elseif ($age <= 59) {
            $ageChartRows[3]['value']++;
        } else {
            $ageChartRows[4]['value']++;
        }
    }

    $knownBirthDateMembers = max(0, $totalMembers - max(0, (int) ($ageChartRows[5]['value'] ?? 0)));

    $renderMemberPieChart = function (string $title, string $ariaLabel, array $rows): void {
        $total = 0;
        foreach ($rows as $row) {
            $total += max(0, (int) ($row['value'] ?? 0));
        }

        echo "  <article class=\"card member-pie-card\">\n";
        echo "    <div class=\"card-row\"><h2>" . h($title) . "</h2></div>\n";
        if ($total <= 0) {
            echo "    <div class=\"chart-empty-inline\">Belum ada data untuk ditampilkan.</div>\n";
            echo "  </article>\n";
            return;
        }

        $size = 220;
        $center = $size / 2;
        $radius = 74;
        $circumference = 2 * pi() * $radius;
        $offset = 0.0;

        echo "    <div class=\"member-pie-layout\">\n";
        echo "      <div class=\"member-pie-stage\" role=\"img\" aria-label=\"" . h($ariaLabel) . "\">\n";
        echo "        <svg class=\"member-pie-svg\" viewBox=\"0 0 " . h((string) $size) . " " . h((string) $size) . "\">\n";
        echo "          <circle class=\"member-pie-track\" cx=\"" . h((string) $center) . "\" cy=\"" . h((string) $center) . "\" r=\"" . h((string) $radius) . "\"></circle>\n";
        echo "          <g transform=\"rotate(-90 " . h((string) $center) . " " . h((string) $center) . ")\">\n";
        foreach ($rows as $row) {
            $value = max(0, (int) ($row['value'] ?? 0));
            if ($value <= 0) {
                continue;
            }
            $portion = $value / $total;
            $length = $portion * $circumference;
            $dash = number_format($length, 2, '.', '') . ' ' . number_format(max($circumference - $length, 0), 2, '.', '');
            $dashOffset = number_format(-$offset, 2, '.', '');
            $offset += $length;
            $stroke = trim((string) ($row['color'] ?? '#94a3b8'));
            if ($stroke === '') {
                $stroke = '#94a3b8';
            }
            $label = trim((string) ($row['label'] ?? '-'));
            if ($label === '') {
                $label = '-';
            }
            $segmentPercent = ($value / $total) * 100;
            $segmentPercentLabel = number_format($segmentPercent, 1, ',', '.');
            $segmentTip = $label . ': ' . $value . ' (' . $segmentPercentLabel . '%)';
            echo "            <circle class=\"member-pie-segment\" cx=\"" . h((string) $center) . "\" cy=\"" . h((string) $center) . "\" r=\"" . h((string) $radius) . "\" stroke=\"" . h($stroke) . "\" stroke-dasharray=\"" . h($dash) . "\" stroke-dashoffset=\"" . h($dashOffset) . "\" tabindex=\"0\" aria-label=\"" . h($segmentTip) . "\" data-member-pie-segment-tip=\"" . h($segmentTip) . "\"><title>" . h($segmentTip) . "</title></circle>\n";
        }
        echo "          </g>\n";
        echo "        </svg>\n";
        echo "        <div class=\"member-pie-center\"><span class=\"value\">" . h((string) $total) . "</span><span class=\"label\">Jemaat Aktif</span></div>\n";
        echo "        <div class=\"member-pie-tooltip\" data-member-pie-tooltip></div>\n";
        echo "      </div>\n";
        echo "      <div class=\"member-pie-legend\">\n";
        foreach ($rows as $row) {
            $value = max(0, (int) ($row['value'] ?? 0));
            $percent = $total > 0 ? ($value / $total) * 100 : 0.0;
            $percentLabel = number_format($percent, 1, ',', '.');
            $label = trim((string) ($row['label'] ?? '-'));
            if ($label === '') {
                $label = '-';
            }
            $color = trim((string) ($row['color'] ?? '#94a3b8'));
            if ($color === '') {
                $color = '#94a3b8';
            }
            echo "        <div class=\"member-pie-legend-item\"><span class=\"dot\" style=\"background:" . h($color) . ";\"></span><span class=\"text\">" . h($label) . "</span><span class=\"count\">" . h((string) $value) . " (" . h($percentLabel) . "%)</span></div>\n";
        }
        echo "      </div>\n";
        echo "    </div>\n";
        echo "  </article>\n";
    };

    echo "<section class=\"card msk-hero-card members-hero-card member-dashboard-hero-card\">\n";
    echo "  <div class=\"msk-hero-head\">\n";
    echo "    <div class=\"msk-hero-copy\">\n";
    echo "      <span class=\"msk-hero-kicker\">Data Jemaat</span>\n";
    echo "      <h1>Dashboard Data Jemaat</h1>\n";
    echo "      <p>Pantau ringkasan jemaat aktif, keluarga, dan persebaran usia dari satu dashboard yang lebih rapi sebelum lanjut ke pendataan atau pembaruan data.</p>\n";
    echo "    </div>\n";
    echo "    <div class=\"msk-hero-stats\" aria-label=\"Ringkasan dashboard data jemaat\">\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Jemaat Aktif</span><strong class=\"msk-hero-stat-value\">" . h((string) $totalMembers) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Jumlah Keluarga</span><strong class=\"msk-hero-stat-value\">" . h((string) $totalFamilies) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Jemaat Keluar</span><strong class=\"msk-hero-stat-value\">" . h((string) $leftMembers) . "</strong></div>\n";
    echo "      <div class=\"msk-hero-stat\"><span class=\"msk-hero-stat-label\">Tanggal Lahir Terisi</span><strong class=\"msk-hero-stat-value\">" . h(number_format($knownBirthDateMembers, 0, ',', '.')) . "</strong></div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<section class=\"member-pie-grid\">\n";
    $renderMemberPieChart('Komposisi Jenis Kelamin', 'Diagram pie komposisi jenis kelamin jemaat aktif', $genderChartRows);
    $renderMemberPieChart('Komposisi Rentang Umur', 'Diagram pie rentang umur jemaat aktif', $ageChartRows);
    echo "</section>\n";
    echo "<script>\n";
    echo "(function () {\n";
    echo "  var stages = document.querySelectorAll('.member-pie-stage');\n";
    echo "  if (!stages || stages.length === 0) {\n";
    echo "    return;\n";
    echo "  }\n";
    echo "  stages.forEach(function (stage) {\n";
    echo "    var tooltip = stage.querySelector('[data-member-pie-tooltip]');\n";
    echo "    var segments = stage.querySelectorAll('.member-pie-segment');\n";
    echo "    if (!tooltip || !segments || segments.length === 0) {\n";
    echo "      return;\n";
    echo "    }\n";
    echo "    var clearActive = function () {\n";
    echo "      segments.forEach(function (segment) {\n";
    echo "        segment.classList.remove('is-active');\n";
    echo "      });\n";
    echo "    };\n";
    echo "    var positionTooltip = function (event) {\n";
    echo "      var rect = stage.getBoundingClientRect();\n";
    echo "      var x;\n";
    echo "      var y;\n";
    echo "      if (event && typeof event.clientX === 'number' && typeof event.clientY === 'number') {\n";
    echo "        x = event.clientX - rect.left + 12;\n";
    echo "        y = event.clientY - rect.top - 14;\n";
    echo "      } else {\n";
    echo "        x = (rect.width - tooltip.offsetWidth) / 2;\n";
    echo "        y = 8;\n";
    echo "      }\n";
    echo "      x = Math.max(8, Math.min(x, rect.width - tooltip.offsetWidth - 8));\n";
    echo "      y = Math.max(8, Math.min(y, rect.height - tooltip.offsetHeight - 8));\n";
    echo "      tooltip.style.left = x + 'px';\n";
    echo "      tooltip.style.top = y + 'px';\n";
    echo "    };\n";
    echo "    var showTooltip = function (segment, event) {\n";
    echo "      var tip = segment.getAttribute('data-member-pie-segment-tip') || '';\n";
    echo "      if (tip === '') {\n";
    echo "        return;\n";
    echo "      }\n";
    echo "      clearActive();\n";
    echo "      segment.classList.add('is-active');\n";
    echo "      tooltip.textContent = tip;\n";
    echo "      tooltip.classList.add('is-visible');\n";
    echo "      positionTooltip(event);\n";
    echo "    };\n";
    echo "    var hideTooltip = function () {\n";
    echo "      clearActive();\n";
    echo "      tooltip.classList.remove('is-visible');\n";
    echo "    };\n";
    echo "    segments.forEach(function (segment) {\n";
    echo "      segment.addEventListener('mouseenter', function (event) {\n";
    echo "        showTooltip(segment, event);\n";
    echo "      });\n";
    echo "      segment.addEventListener('mousemove', function (event) {\n";
    echo "        if (tooltip.classList.contains('is-visible')) {\n";
    echo "          positionTooltip(event);\n";
    echo "        }\n";
    echo "      });\n";
    echo "      segment.addEventListener('mouseleave', hideTooltip);\n";
    echo "      segment.addEventListener('focus', function () {\n";
    echo "        showTooltip(segment, null);\n";
    echo "      });\n";
    echo "      segment.addEventListener('blur', hideTooltip);\n";
    echo "    });\n";
    echo "  });\n";
    echo "})();\n";
    echo "</script>\n";

    page_footer();
    legacy_exit();
}
