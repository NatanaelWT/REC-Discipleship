<?php

return [
    'private_root' => env('REC_PRIVATE_STORAGE_PATH', storage_path('app/private')),
    'dg_meeting_report_max_bytes' => max(1, (int) env('DG_MEETING_REPORT_PHOTO_MAX_BYTES', 20 * 1024 * 1024)),
    'original_max_side' => 20000,
    'original_max_pixels' => 100_000_000,
    'secure_url_minutes' => max(1, (int) env('MEDIA_SECURE_URL_MINUTES', 30)),
];
