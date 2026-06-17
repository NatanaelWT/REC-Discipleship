<?php

function build_worship_penatalayan_schedule(string $monthValue, ?array $existing = null): array {
    $monthValue = normalize_month_value($monthValue);
    $weekDates = worship_penatalayan_week_dates($monthValue);
    $schedule = [
        'month' => $monthValue,
        'title' => trim((string) ($existing['title'] ?? default_worship_penatalayan_title($monthValue))),
        'update_note' => trim((string) ($existing['update_note'] ?? '')),
        'rows' => normalize_worship_penatalayan_rows($existing['rows'] ?? [], count($weekDates)),
        'created_at' => trim((string) ($existing['created_at'] ?? '')),
        'updated_at' => trim((string) ($existing['updated_at'] ?? '')),
        'week_dates' => $weekDates,
    ];
    if ($schedule['title'] === '') {
        $schedule['title'] = default_worship_penatalayan_title($monthValue);
    }
    return $schedule;
}
