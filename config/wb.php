<?php

return [
    'base_url' => env('WB_API_BASE_URL', 'http://109.73.206.144:6969'),
    'key' => env('WB_API_KEY'),
    'limit' => (int) env('WB_API_LIMIT', 500),
    'timeout' => (int) env('WB_API_TIMEOUT', 120),
    'date_from' => env('WB_SYNC_DATE_FROM', '2025-05-01'),
    'date_to' => env('WB_SYNC_DATE_TO'),
    'request_delay_ms' => (int) env('WB_REQUEST_DELAY_MS', 350),
    'retry_attempts' => (int) env('WB_RETRY_ATTEMPTS', 5),
    'retry_base_seconds' => (int) env('WB_RETRY_BASE_SECONDS', 2),
];
