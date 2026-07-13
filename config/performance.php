<?php

return [
    'enabled' => env('PERFORMANCE_MONITORING', false),
    'slow_request_ms' => (int) env('PERFORMANCE_SLOW_REQUEST_MS', 1000),
    'log_all' => env('PERFORMANCE_LOG_ALL', false),
];
