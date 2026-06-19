<?php

function discipleship_table_now_iso(): string {
    if (function_exists('now_iso')) {
        return now_iso();
    }
    return (new DateTimeImmutable('now', app_timezone()))->format('Y-m-d\TH:i:sP');
}
