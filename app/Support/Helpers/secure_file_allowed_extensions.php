<?php

function secure_file_allowed_extensions(): array {
    static $allowed = [
        'pdf' => true,
        'doc' => true,
        'docx' => true,
        'xls' => true,
        'xlsx' => true,
        'ppt' => true,
        'pptx' => true,
        'txt' => true,
        'csv' => true,
        'jpg' => true,
        'png' => true,
        'webp' => true,
        'gif' => true,
        'zip' => true,
        'rar' => true,
    ];
    return $allowed;
}
