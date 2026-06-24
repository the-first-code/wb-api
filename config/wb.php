<?php

return [
    'base_url' => env('WB_API_BASE_URL', 'http://109.73.206.144:6969'),
    'key' => env('WB_API_KEY'),
    'api_service_code' => env('WB_API_SERVICE', 'wb_test'),
    'limit' => (int) env('WB_API_LIMIT', 500),
    'timeout' => (int) env('WB_API_TIMEOUT', 120),
    'date_from' => env('WB_SYNC_DATE_FROM', '2025-05-01'),
    'date_to' => env('WB_SYNC_DATE_TO'),
    'fresh_only' => (bool) env('WB_SYNC_FRESH_ONLY', false),
    'fresh_overlap_days' => (int) env('WB_SYNC_FRESH_OVERLAP_DAYS', 1),
    'fresh_initial_days' => (int) env('WB_SYNC_FRESH_INITIAL_DAYS', 31),
    'request_delay_ms' => (int) env('WB_REQUEST_DELAY_MS', 350),
    'retry_attempts' => (int) env('WB_RETRY_ATTEMPTS', 5),
    'retry_base_seconds' => (int) env('WB_RETRY_BASE_SECONDS', 2),
    'retry_max_seconds' => (int) env('WB_RETRY_MAX_SECONDS', 60),
    'rate_limit_penalty_ms' => (int) env('WB_RATE_LIMIT_PENALTY_MS', 1000),
    'debug' => filter_var(env('WB_DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    'schedule_hour_1' => (int) env('WB_SYNC_SCHEDULE_HOUR_1', 8),
    'schedule_hour_2' => (int) env('WB_SYNC_SCHEDULE_HOUR_2', 20),
    'schedule_lookback_days' => (int) env('WB_SYNC_SCHEDULE_LOOKBACK_DAYS', 31),
];
