<?php

return [
    'private_root' => env('REC_PRIVATE_STORAGE_PATH', storage_path('app/private')),
    'web_max_side' => 1920,
    'thumbnail_max_side' => 480,
    'web_quality' => 82,
    'original_max_side' => 20000,
    'original_max_pixels' => 100_000_000,
    'quarantine_days' => 30,
    'orphan_grace_hours' => 24,
    'pdf_text_max_bytes' => 15 * 1024 * 1024,
    'secure_url_minutes' => max(1, (int) env('MEDIA_SECURE_URL_MINUTES', 30)),
];
