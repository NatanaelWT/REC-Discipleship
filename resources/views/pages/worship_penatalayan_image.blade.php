<?php

if ($page === 'worship_penatalayan_image') {
    $selectedMonth = normalize_month_value((string) ($_GET['month'] ?? date('Y-m')));
    $selectedExistingSchedule = null;
    foreach ($worshipPenatalayanSchedules as $scheduleRow) {
        if (normalize_month_value((string) ($scheduleRow['month'] ?? date('Y-m'))) === $selectedMonth) {
            $selectedExistingSchedule = $scheduleRow;
            break;
        }
    }
    if ($selectedExistingSchedule === null) {
        redirect_to('worship_penatalayan', ['error' => 'invalid_schedule', 'month' => $selectedMonth]);
    }

    $schedule = build_worship_penatalayan_schedule($selectedMonth, $selectedExistingSchedule);
    $pngContent = render_worship_penatalayan_schedule_png($schedule);
    if (!is_string($pngContent) || $pngContent === '') {
        redirect_to('worship_penatalayan', ['error' => 'invalid_schedule', 'month' => $selectedMonth]);
    }
    $downloadName = 'penatalayan-ibadah-umum-' . $selectedMonth . '.png';
    $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', $downloadName) ?? 'penatalayan-ibadah.png';
    if ($downloadName === '') {
        $downloadName = 'penatalayan-ibadah.png';
    }
    $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'penatalayan-ibadah.png';
    if ($asciiDownloadName === '') {
        $asciiDownloadName = 'penatalayan-ibadah.png';
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Disposition: attachment; filename="' . $asciiDownloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    echo $pngContent;
    legacy_exit();
}
