<?php

return [
    // The campaign slogan, used on the homepage, page titles, the card, share messages and the DP frame.
    'slogan' => 'Hope Renewed, Nation Rebuilt.',

    // Official social media accounts, shown on the homepage. Facebook and Instagram point at posts until the
    // profile links are known; set SOCIAL_FACEBOOK_URL / SOCIAL_INSTAGRAM_URL in .env to change them.
    'social' => [
        'facebook' => env('SOCIAL_FACEBOOK_URL', 'https://www.facebook.com/share/p/1ChpUEEXha/'),
        'instagram' => env('SOCIAL_INSTAGRAM_URL', 'https://www.instagram.com/p/DfrQU7rxU0-/'),
        'tiktok' => env('SOCIAL_TIKTOK_URL', 'https://www.tiktok.com/@renewedhopeyouthe'),
        'x' => env('SOCIAL_X_URL', 'https://x.com/rhyengagement'),
    ],

    // Where "Get help" on the sign-in and join screens points (a WhatsApp or mailto: link, for example).
    'help_url' => env('HELP_URL'),

    // Dates and times are stored in UTC and shown in this zone.
    'timezone' => 'Africa/Lagos',

    // Raise when the data-consent wording on /join or /privacy changes.
    'consent_version' => env('MEMBERSHIP_CONSENT_VERSION', '1'),

    // Sign-up submissions per IP per minute. Nigerian mobile networks put many
    // phones behind one address, so keep this generous for rallies.
    'join_rate_limit_per_minute' => (int) env('JOIN_RATE_LIMIT_PER_MINUTE', 30),

    'empowerment_photo_max_kb' => (int) env('EMPOWERMENT_PHOTO_MAX_KB', 8192),
];
