<?php

function legacy_exit(string $message = ''): void {
    if ($message !== '') {
        echo $message;
    }
    if (class_exists(\App\Support\LegacyExit::class)) {
        throw new \App\Support\LegacyExit();
    }
    exit;
}
