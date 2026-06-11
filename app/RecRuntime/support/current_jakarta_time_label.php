<?php

function current_jakarta_time_label(): string {
    return (new DateTimeImmutable('now', app_timezone()))->format('H:i:s') . ' WIB';
}
