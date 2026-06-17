<?php

function format_datetime_id(string $value): string {
    $timestamp = strtotime(trim($value));
    if ($timestamp === false) {
        return '-';
    }
    return date('d-m-Y H:i', $timestamp);
}
