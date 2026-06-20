<?php

return [
    'enabled' => env('PERFORMANCE_MONITORING', false),
    'slow_request_ms' => (int) env('PERFORMANCE_SLOW_REQUEST_MS', 1000),
];
