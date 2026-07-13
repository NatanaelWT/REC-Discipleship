<?php

$defaultStorage = env('APP_ENV') === 'testing' ? 'legacy' : 'split';

return [
    'enabled' => env('ACTIVITY_ENABLED', true),
    'storage' => env('ACTIVITY_STORAGE', $defaultStorage),
    'retention_days' => max(1, (int) env('ACTIVITY_RETENTION_DAYS', 90)),
    'spool' => [
        'directory' => env('ACTIVITY_SPOOL_DIRECTORY', 'activity-spool'),
        'max_bytes' => max(1_048_576, (int) env('ACTIVITY_SPOOL_MAX_BYTES', 536_870_912)),
        'replay_batch' => max(10, (int) env('ACTIVITY_SPOOL_REPLAY_BATCH', 250)),
    ],
    'maintenance' => [
        'batch_size' => max(10, (int) env('ACTIVITY_MAINTENANCE_BATCH_SIZE', 500)),
        'batch_seconds' => max(1, min(9, (int) env('ACTIVITY_MAINTENANCE_BATCH_SECONDS', 8))),
        'lock_seconds' => max(10, (int) env('ACTIVITY_MAINTENANCE_LOCK_SECONDS', 30)),
    ],
];
