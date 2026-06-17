<?php

function worship_penatalayan_training_label(string $value, string $monthValue = ''): string {
    $dateValue = worship_penatalayan_training_date($value, $monthValue);
    if ($dateValue !== '') {
        return format_short_indo_weekday_date($dateValue);
    }
    $value = preg_replace("/\s+/", ' ', str_replace(["\r", "\n"], ' ', trim($value))) ?? trim($value);
    return $value;
}
