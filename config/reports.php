<?php

return [
    'export_sync_limit' => (int) env('EXPORT_SYNC_LIMIT', config('thresholds.async_processing_limit', 500)),
    'export_ttl_hours'  => (int) env('EXPORT_TTL_HOURS', 24),
    'storage_disk'      => env('EXPORT_STORAGE_DISK', 'local'),
    'max_period_days'   => (int) env('EXPORT_MAX_PERIOD_DAYS', 366),
    'allowed_periods'   => ['last_month', 'previous_month', '3_months', '6_months', '1_year', 'this_year', 'custom'],
];
