<?php

function render_worship_penatalayan_schedule_png(array $schedule): ?string {
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }

    $weekDates = is_array($schedule['week_dates'] ?? null) ? $schedule['week_dates'] : [];
    $rows = is_array($schedule['rows'] ?? null) ? $schedule['rows'] : [];
    $weekCount = max(1, count($weekDates));
    $margin = 24;
    $roleColumnWidth = 180;
    $weekColumnWidth = $weekCount >= 5 ? 132 : 148;
    $tableWidth = $roleColumnWidth + ($weekCount * $weekColumnWidth);

    $titleText = trim((string) ($schedule['title'] ?? default_worship_penatalayan_title((string) ($schedule['month'] ?? date('Y-m')))));
    if ($titleText === '') {
        $titleText = default_worship_penatalayan_title((string) ($schedule['month'] ?? date('Y-m')));
    }
    $updateText = trim((string) ($schedule['update_note'] ?? ''));
    $titleLines = worship_penatalayan_svg_wrap_lines($titleText, 54);
    $updateLines = $updateText !== '' ? worship_penatalayan_svg_wrap_lines($updateText, 48) : [];
    $titleBlockHeight = 26 + (count($titleLines) * 28) + (count($updateLines) > 0 ? 12 + (count($updateLines) * 18) : 0);

    $fontRegular = worship_penatalayan_font_path(false);
    $fontBold = worship_penatalayan_font_path(true);
    if ($fontBold === '') {
        $fontBold = $fontRegular;
    }

    $headerHeight = 50;
    $rowLayouts = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $roleLabel = trim((string) ($row['role'] ?? '-'));
        if ($roleLabel === '') {
            $roleLabel = '-';
        }
        $roleLayout = worship_penatalayan_png_text_layout($roleLabel, $roleColumnWidth - 18, 13, $fontBold, 16);
        $assignments = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
        $cellLayoutsByWeek = [];
        $maxBlockHeight = (float) ($roleLayout['height'] ?? 16);
        $isTrainingSchedule = strtolower($roleLabel) === 'jadwal latihan';
        for ($weekIndex = 0; $weekIndex < $weekCount; $weekIndex++) {
            $cellValue = trim((string) ($assignments[$weekIndex] ?? ''));
            if ($isTrainingSchedule) {
                $cellValue = worship_penatalayan_training_label($cellValue, (string) ($schedule['month'] ?? ''));
            }
            $cellLayout = $cellValue !== ''
                ? worship_penatalayan_png_text_layout(
                    $cellValue,
                    $weekColumnWidth - 16,
                    $isTrainingSchedule ? 11 : 13,
                    $fontRegular,
                    $isTrainingSchedule ? 13 : 16,
                    $isTrainingSchedule ? 0 : 4
                )
                : ['lines' => [''], 'extra_gaps' => [], 'height' => 16];
            $cellLayoutsByWeek[] = $cellLayout;
            $maxBlockHeight = max($maxBlockHeight, (float) ($cellLayout['height'] ?? 16));
        }
        $rowHeight = max(34, (int) ceil($maxBlockHeight + 10));
        $rowLayouts[] = [
            'role' => $roleLabel,
            'role_layout' => $roleLayout,
            'cell_layouts' => $cellLayoutsByWeek,
            'height' => $rowHeight,
        ];
    }

    $tableHeight = $headerHeight;
    foreach ($rowLayouts as $layout) {
        $tableHeight += (int) ($layout['height'] ?? 0);
    }
    $imageWidth = ($margin * 2) + $tableWidth;
    $imageHeight = ($margin * 2) + $titleBlockHeight + 18 + $tableHeight;

    $image = imagecreatetruecolor($imageWidth, $imageHeight);
    if ($image === false) {
        return null;
    }
    imageantialias($image, true);
    $white = imagecolorallocate($image, 255, 255, 255);
    $titleBg = imagecolorallocate($image, 229, 231, 235);
    $headerBg = imagecolorallocate($image, 209, 213, 219);
    $roleBg = imagecolorallocate($image, 229, 231, 235);
    $bodyBg = imagecolorallocate($image, 243, 244, 246);
    $trainingBg = imagecolorallocate($image, 254, 243, 199);
    $dark = imagecolorallocate($image, 17, 24, 39);
    $muted = imagecolorallocate($image, 71, 85, 105);
    imagefill($image, 0, 0, $white);

    imagefilledrectangle($image, $margin, $margin, $margin + $tableWidth, $margin + $titleBlockHeight, $titleBg);

    $titleY = $margin + 18;
    worship_penatalayan_png_draw_text($image, $titleLines, $margin + ($tableWidth / 2), $titleY, $dark, [
        'anchor' => 'middle',
        'size' => 24,
        'line_height' => 28,
        'font' => $fontBold,
    ]);
    if (count($updateLines) > 0) {
        $updateY = $titleY + (count($titleLines) * 28) + 8;
        worship_penatalayan_png_draw_text($image, $updateLines, $margin + ($tableWidth / 2), $updateY, $muted, [
            'anchor' => 'middle',
            'size' => 16,
            'line_height' => 18,
            'font' => $fontRegular,
        ]);
    }

    $tableX = $margin;
    $tableY = $margin + $titleBlockHeight + 18;
    imagefilledrectangle($image, $tableX, $tableY, $tableX + $tableWidth, $tableY + $tableHeight, $bodyBg);
    imagefilledrectangle($image, $tableX, $tableY, $tableX + $tableWidth, $tableY + $headerHeight, $headerBg);

    $rowY = $tableY + $headerHeight;
    foreach ($rowLayouts as $layout) {
        $rowHeight = (int) ($layout['height'] ?? 34);
        $roleLabel = strtolower((string) ($layout['role'] ?? ''));
        imagefilledrectangle($image, $tableX, $rowY, $tableX + $tableWidth, $rowY + $rowHeight, $bodyBg);
        if ($roleLabel === 'jadwal latihan') {
            imagefilledrectangle($image, $tableX, $rowY, $tableX + $tableWidth, $rowY + $rowHeight, $trainingBg);
        } else {
            imagefilledrectangle($image, $tableX, $rowY, $tableX + $roleColumnWidth, $rowY + $rowHeight, $roleBg);
        }
        $rowY += $rowHeight;
    }

    imagesetthickness($image, 1);
    imagerectangle($image, $tableX, $tableY, $tableX + $tableWidth, $tableY + $tableHeight, $dark);
    $roleBoundaryX = $tableX + $roleColumnWidth;
    imageline($image, $roleBoundaryX, $tableY, $roleBoundaryX, $tableY + $tableHeight, $dark);
    for ($weekIndex = 1; $weekIndex < $weekCount; $weekIndex++) {
        $colX = $tableX + $roleColumnWidth + ($weekIndex * $weekColumnWidth);
        imageline($image, $colX, $tableY, $colX, $tableY + $tableHeight, $dark);
    }
    $currentY = $tableY + $headerHeight;
    imageline($image, $tableX, $currentY, $tableX + $tableWidth, $currentY, $dark);
    foreach ($rowLayouts as $layout) {
        $currentY += (int) ($layout['height'] ?? 0);
        imageline($image, $tableX, $currentY, $tableX + $tableWidth, $currentY, $dark);
    }

    $headerRoleLayout = worship_penatalayan_png_text_layout('PELAYAN', $roleColumnWidth - 10, 14, $fontBold, 16);
    worship_penatalayan_png_draw_text($image, $headerRoleLayout['lines'], $tableX + ($roleColumnWidth / 2), $tableY + (($headerHeight - $headerRoleLayout['height']) / 2), $dark, [
        'anchor' => 'middle',
        'size' => 14,
        'line_height' => 16,
        'font' => $fontBold,
        'extra_gaps' => $headerRoleLayout['extra_gaps'],
    ]);
    foreach ($weekDates as $weekIndex => $weekDate) {
        $colX = $tableX + $roleColumnWidth + ($weekIndex * $weekColumnWidth);
        $headerDateLayout = worship_penatalayan_png_text_layout(format_short_indo_weekday_date((string) $weekDate), $weekColumnWidth - 10, 12, $fontBold, 16);
        worship_penatalayan_png_draw_text($image, $headerDateLayout['lines'], $colX + ($weekColumnWidth / 2), $tableY + (($headerHeight - $headerDateLayout['height']) / 2), $dark, [
            'anchor' => 'middle',
            'size' => 12,
            'line_height' => 16,
            'font' => $fontBold,
            'extra_gaps' => $headerDateLayout['extra_gaps'],
        ]);
    }

    $rowTextY = $tableY + $headerHeight;
    foreach ($rowLayouts as $layout) {
        $rowHeight = (int) ($layout['height'] ?? 34);
        $roleLayout = is_array($layout['role_layout'] ?? null) ? $layout['role_layout'] : ['lines' => [''], 'extra_gaps' => [], 'height' => 16];
        worship_penatalayan_png_draw_text($image, $roleLayout['lines'], $tableX + 10, $rowTextY + (($rowHeight - (float) ($roleLayout['height'] ?? 16)) / 2), $dark, [
            'anchor' => 'start',
            'size' => 13,
            'line_height' => 16,
            'font' => $fontBold,
            'extra_gaps' => $roleLayout['extra_gaps'] ?? [],
        ]);
        foreach ($layout['cell_layouts'] ?? [] as $weekIndex => $cellLayout) {
            $colX = $tableX + $roleColumnWidth + ($weekIndex * $weekColumnWidth);
            $cellLayout = is_array($cellLayout) ? $cellLayout : ['lines' => [''], 'extra_gaps' => [], 'height' => 16];
            $isTrainingSchedule = strtolower((string) ($layout['role'] ?? '')) === 'jadwal latihan';
            worship_penatalayan_png_draw_text($image, $cellLayout['lines'], $colX + ($weekColumnWidth / 2), $rowTextY + (($rowHeight - (float) ($cellLayout['height'] ?? 16)) / 2), $dark, [
                'anchor' => 'middle',
                'size' => $isTrainingSchedule ? 11 : 13,
                'line_height' => $isTrainingSchedule ? 13 : 16,
                'font' => $fontRegular,
                'extra_gaps' => $cellLayout['extra_gaps'] ?? [],
            ]);
        }
        $rowTextY += $rowHeight;
    }

    ob_start();
    imagepng($image);
    $pngBinary = ob_get_clean();
    imagedestroy($image);
    return is_string($pngBinary) ? $pngBinary : null;
}
