<?php

function next_saturday_date(string $fromDate = ''): string {
    $baseDate = $fromDate !== '' ? normalize_ymd_date($fromDate) : today_date();
    if ($baseDate === '') {
        $baseDate = today_date();
    }
    $timestamp = strtotime($baseDate);
    if ($timestamp === false) {
        return today_date();
    }
    $dayNumber = (int) date('w', $timestamp);
    $daysToAdd = (6 - $dayNumber + 7) % 7;
    return date('Y-m-d', strtotime('+' . $daysToAdd . ' day', $timestamp));
}
