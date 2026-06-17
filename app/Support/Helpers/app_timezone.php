<?php

function app_timezone(): DateTimeZone {
    static $timezone = null;
    if (!$timezone instanceof DateTimeZone) {
        $timezone = new DateTimeZone(APP_TIMEZONE);
    }
    return $timezone;
}
