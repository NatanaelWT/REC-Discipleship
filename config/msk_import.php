<?php

return [
    'disk' => env('MSK_IMPORT_DISK', 'local'),
    'batch_size' => max(1, min(500, (int) env('MSK_IMPORT_BATCH_SIZE', 500))),
    'batch_seconds' => max(1, min(9, (int) env('MSK_IMPORT_BATCH_SECONDS', 8))),
    'lock_seconds' => max(15, (int) env('MSK_IMPORT_LOCK_SECONDS', 60)),
    'max_file_bytes' => max(1024, (int) env('MSK_IMPORT_MAX_FILE_BYTES', 10 * 1024 * 1024)),
    'max_errors' => max(1, min(250, (int) env('MSK_IMPORT_MAX_ERRORS', 100))),
];
