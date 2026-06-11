<?php

function default_worship_penatalayan_title(string $monthValue): string {
    return 'Jadwal Pelayanan Ibadah Umum ' . format_indo_month($monthValue);
}
