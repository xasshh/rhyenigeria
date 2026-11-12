<?php

return [
    // Dates and times are stored in UTC and shown in this zone.
    'timezone' => 'Africa/Lagos',

    // Raise when the data-consent wording on /join or /privacy changes.
    'consent_version' => env('MEMBERSHIP_CONSENT_VERSION', '1'),

    // Sign-up submissions per IP per minute. Nigerian mobile networks put many
    // phones behind one address, so keep this generous for rallies.
    'join_rate_limit_per_minute' => (int) env('JOIN_RATE_LIMIT_PER_MINUTE', 30),

    'empowerment_photo_max_kb' => (int) env('EMPOWERMENT_PHOTO_MAX_KB', 8192),
];
