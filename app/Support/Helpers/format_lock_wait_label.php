<?php

function format_lock_wait_label(int $seconds): string {
    $seconds = max(0, $seconds);
    if ($seconds <= 0) {
        return 'beberapa saat';
    }
    $minutes = (int) floor($seconds / 60);
    $remainSeconds = $seconds % 60;
    if ($minutes <= 0) {
        return $remainSeconds . ' detik';
    }
    if ($remainSeconds === 0) {
        return $minutes . ' menit';
    }
    return $minutes . ' menit ' . $remainSeconds . ' detik';
}
