<?php

return [
    // The slogan, social links, names and logos are per site: see config/site.php.

    // Where "Get help" on the sign-in and join screens points (a WhatsApp or mailto: link, for example).
    'help_url' => env('HELP_URL'),

    // Dates and times are stored in UTC and shown in this zone.
    'timezone' => 'Africa/Lagos',

    // Raise when the data-consent wording on /join or /privacy changes.
    'consent_version' => env('MEMBERSHIP_CONSENT_VERSION', '1'),

    // Sign-up submissions per IP per minute. Nigerian mobile networks put many
    // phones behind one address, so keep this generous for rallies.
    // At a rally hundreds of phones can share one IP (venue Wi-Fi, carrier NAT), so these are per IP and generous.
    // One sign-up makes about 4 step checks and 3 dropdown lookups.
    'join_rate_limit_per_minute' => (int) env('JOIN_RATE_LIMIT_PER_MINUTE', 120),
    'join_check_rate_limit_per_minute' => (int) env('JOIN_CHECK_RATE_LIMIT_PER_MINUTE', 600),
    'geo_rate_limit_per_minute' => (int) env('GEO_RATE_LIMIT_PER_MINUTE', 1200),

    // Members one canvasser can sign up from the field page per hour (people without a smartphone).
    'field_registrations_per_hour' => (int) env('FIELD_REGISTRATIONS_PER_HOUR', 40),

    'empowerment_photo_max_kb' => (int) env('EMPOWERMENT_PHOTO_MAX_KB', 8192),
];
