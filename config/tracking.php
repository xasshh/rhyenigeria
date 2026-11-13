<?php

return [

    // Location points older than this many days are deleted by `tracking:prune`.
    'retention_days' => (int) env('TRACKING_RETENTION_DAYS', 90),

    // Location batches an agent may post per minute.
    'rate_limit_per_minute' => (int) env('TRACKING_RATE_LIMIT_PER_MINUTE', 60),

    // Maximum number of points accepted in one batch.
    'max_batch_size' => (int) env('TRACKING_MAX_BATCH_SIZE', 500),

    // Points time-stamped further ahead of the server clock than this are dropped.
    'max_clock_skew_seconds' => 300,

    // Version of the tracking consent notice agents are asked to accept.
    'consent_version' => (string) env('TRACKING_CONSENT_VERSION', '1'),

    // Street map behind the coordinators' live map (MapLibre style URL). OpenFreeMap: OpenStreetMap data, no key.
    'map_style_url' => env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/liberty'),

    // Longest window a single trail request may cover.
    'max_trail_window_days' => 31,

];
