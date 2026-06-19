<?php

function app_timezone(): DateTimeZone {
    $timezoneName = app_config_value('app_timezone', defined('APP_TIMEZONE') ? (string) APP_TIMEZONE : 'Asia/Jakarta');
    try {
        return new DateTimeZone($timezoneName);
    } catch (Throwable) {
        return new DateTimeZone('Asia/Jakarta');
    }
}
