<?php

function normalize_worship_penatalayan_records($records): array {
    if (!is_array($records)) {
        return [];
    }
    $normalizedByMonth = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $monthValue = normalize_month_value((string) ($record['month'] ?? date('Y-m')));
        $weekDates = worship_penatalayan_week_dates($monthValue);
        $normalizedByMonth[$monthValue] = [
            'month' => $monthValue,
            'title' => trim((string) ($record['title'] ?? default_worship_penatalayan_title($monthValue))),
            'update_note' => trim((string) ($record['update_note'] ?? '')),
            'rows' => normalize_worship_penatalayan_rows($record['rows'] ?? [], count($weekDates)),
            'created_at' => trim((string) ($record['created_at'] ?? '')),
            'updated_at' => trim((string) ($record['updated_at'] ?? '')),
        ];
        if ($normalizedByMonth[$monthValue]['title'] === '') {
            $normalizedByMonth[$monthValue]['title'] = default_worship_penatalayan_title($monthValue);
        }
    }
    krsort($normalizedByMonth);
    return array_values($normalizedByMonth);
}
