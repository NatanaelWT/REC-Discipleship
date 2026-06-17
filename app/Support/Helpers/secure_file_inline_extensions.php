<?php

function secure_file_inline_extensions(): array {
    static $inline = [
        'pdf' => true,
        'txt' => true,
        'csv' => true,
        'jpg' => true,
        'png' => true,
        'webp' => true,
        'gif' => true,
    ];
    return $inline;
}
