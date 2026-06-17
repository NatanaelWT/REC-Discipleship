<?php

function now_iso(): string {
    return (new DateTimeImmutable('now', app_timezone()))->format('Y-m-d\TH:i:sP');
}
