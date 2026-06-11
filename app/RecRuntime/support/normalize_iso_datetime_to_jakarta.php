<?php

function normalize_iso_datetime_to_jakarta(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    try {
        $dateTime = new DateTimeImmutable($value, app_timezone());
    } catch (Exception $exception) {
        return '';
    }
    return $dateTime->setTimezone(app_timezone())->format('Y-m-d\TH:i:sP');
}
