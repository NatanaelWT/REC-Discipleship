<?php

return [
    'max_file_bytes' => max(1024, (int) env('MSK_IMPORT_MAX_FILE_BYTES', 10 * 1024 * 1024)),
    'max_rows' => max(1, min(5000, (int) env('MSK_IMPORT_MAX_ROWS', 5000))),
    'max_errors' => max(1, min(250, (int) env('MSK_IMPORT_MAX_ERRORS', 100))),
    'lock_seconds' => max(60, min(300, (int) env('MSK_IMPORT_LOCK_SECONDS', 300))),
];
