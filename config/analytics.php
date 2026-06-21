<?php

return [
    'enabled' => env('ANALYTICS_ENABLED', true),
    'cookie' => [
        'name' => env('ANALYTICS_COOKIE', 'rec_analytics_visitor'),
        'minutes' => (int) env('ANALYTICS_COOKIE_MINUTES', 525600),
    ],
    'session_inactivity_minutes' => (int) env('ANALYTICS_SESSION_MINUTES', 30),
    'dashboard_cache_seconds' => (int) env('ANALYTICS_CACHE_SECONDS', 60),
];
