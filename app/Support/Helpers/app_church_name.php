<?php

function app_church_name(): string {
    $fallback = defined('CHURCH_NAME') ? (string) CHURCH_NAME : 'Reformed Exodus Community';
    $churchName = trim(app_config_value('church_name', $fallback));

    return $churchName !== '' ? $churchName : $fallback;
}
