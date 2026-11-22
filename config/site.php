<?php

/*
 * One codebase, several campaign sites. SITE_BRAND in .env picks which one this server is; everything that
 * differs between them (names, logos, slogan, wording, whether the site covers one state) lives here.
 * Read values as config('site.name'), config('site.slogan'), and so on.
 */

$brands = [
    // rhyenigeria.org: nationwide youth movement.
    'rhye' => [
        'name' => 'Renewed Hope Youth Engagement',
        'short' => 'RHYE',
        // Membership numbers look like RHYE-LA-004821.
        'member_prefix' => 'RHYE',
        'slogan' => 'Hope Renewed, Nation Rebuilt.',
        'tagline' => 'Renewed Hope Youth Engagement · Youth for PBAT',
        'description' => 'Join the movement. Get your digital membership card and updates from your state.',
        // [path, width, height] of the main logo, then any partner logos shown beside it in the header.
        'logo' => ['images/logo-rhye.png', 600, 272],
        // Whether the logo already spells out the name; when false the header writes the name beside it.
        'logo_has_name' => true,
        // Folder (under public/) with favicon-32.png, apple-touch-icon.png and, optionally, favicon.ico / favicon-48.png.
        'icons' => '',
        'partner_logos' => [
            ['images/logo-renewed-hope.png', 285, 150, 'Renewed Hope', 'h-[30px] sm:h-[37.5px]'],
            ['images/logo-apc.png', 200, 182, 'APC', 'h-[38px] sm:h-[45.5px]'],
        ],
        // null: every state; a state code (e.g. KD): the whole site is about that one state.
        'state_code' => null,
        // What approved field volunteers are called.
        'canvasser' => 'canvasser',
        'canvassers' => 'canvassers',
        // null: volunteers pick how they will help; a list: everyone gets these roles and the picker is skipped.
        'canvasser_roles' => null,
        'social' => [
            'facebook' => env('SOCIAL_FACEBOOK_URL', 'https://www.facebook.com/share/p/1ChpUEEXha/'),
            'instagram' => env('SOCIAL_INSTAGRAM_URL', 'https://www.instagram.com/p/DfrQU7rxU0-/'),
            'tiktok' => env('SOCIAL_TIKTOK_URL', 'https://www.tiktok.com/@renewedhopeyouthe'),
            'x' => env('SOCIAL_X_URL', 'https://x.com/rhyengagement'),
        ],
        // Campaign DP frame messages.
        'dp_messages' => [
            ['kicker' => 'I am a proud member of the', 'headline' => 'Renewed Hope Youth Engagement movement'],
            ['kicker' => 'Youth for PBAT', 'headline' => 'Hope Renewed, Nation Rebuilt.'],
            ['kicker' => 'Renewed Hope Youth Engagement', 'headline' => 'Every ward. Every youth.'],
        ],
        // The three figures beside the homepage map.
        // The example membership card on the sign-in and join screens: [name, state, ward].
        'sample_card' => ['Adaeze Okafor', 'Lagos', 'Ikeja · Ward 04'],
        'stats' => [['5,000+', 'youth empowered'], ['275+', 'events organised'], ['36+', 'states nationwide']],
        // The three badges around the President's picture on the main homepage slide.
        'hero_badges' => ['5,000+ youth empowered', '275+ Events Organized', '36+ Nationwide'],
    ],

    // ubasani2027.com: Kaduna State governorship campaign.
    'us27' => [
        'name' => 'Uba Sani 2027 Movement',
        'short' => 'US27',
        'member_prefix' => 'US27',
        'slogan' => 'Tinubu, Uba Sau Biyu ne!',
        'tagline' => 'Uba Sani 2027 Movement · Kaduna State',
        'description' => 'Join the Uba Sani 2027 Movement in Kaduna State. Get your digital membership card and updates from your ward.',
        'logo' => ['images/logo-apc.png', 200, 182],
        'logo_has_name' => false,
        'icons' => 'icons/us27',
        'partner_logos' => [],
        'state_code' => 'KD',
        'canvasser' => 'polling unit agent',
        'canvassers' => 'polling unit agents',
        'canvasser_roles' => ['polling_unit_agent'],
        'social' => [
            'facebook' => env('SOCIAL_FACEBOOK_URL'),
            'instagram' => env('SOCIAL_INSTAGRAM_URL'),
            'tiktok' => env('SOCIAL_TIKTOK_URL'),
            'x' => env('SOCIAL_X_URL'),
        ],
        'dp_messages' => [
            ['kicker' => 'I am a proud member of the', 'headline' => 'Uba Sani 2027 Movement'],
            ['kicker' => 'Kaduna State', 'headline' => 'Tinubu, Uba Sau Biyu ne!'],
            ['kicker' => 'Uba Sani 2027 Movement', 'headline' => 'Every ward. Every polling unit.'],
        ],
        'sample_card' => ['Aisha Bello', 'Kaduna', 'Kaduna North · Ward 04'],
        'stats' => [['23', 'local governments'], ['255', 'wards'], ['8,000+', 'polling units']],
        'hero_badges' => ['23 local governments', '255 wards', '8,000+ polling units'],
        // Homepage slides for a new database (php artisan site:seed-slides). Admins edit them afterwards.
        'slides' => [
            [
                'layout' => 'classic', 'eyebrow' => 'Kaduna State', 'headline' => 'Tinubu, Uba Sau Biyu ne!',
                'body' => 'Sign up once and get a digital membership card, updates from your ward, and a direct line to your polling unit team.',
                'image_path' => 'images/slides/tinubu-portrait.webp', 'image_alt' => 'President Bola Ahmed Tinubu',
                'button_label' => 'Join the Movement', 'button_url' => '/join',
            ],
            [
                'layout' => 'portrait', 'eyebrow' => 'Governor of Kaduna State', 'headline' => 'Senator Uba Sani',
                'body' => 'Executive Governor of Kaduna State.',
                'image_path' => 'images/slides/governor-placeholder.svg', 'image_alt' => 'Senator Uba Sani',
                'button_label' => 'Join the Movement', 'button_url' => '/join',
            ],
        ],
    ],
];

$key = env('SITE_BRAND', 'rhye');

return ['key' => $key] + ($brands[$key] ?? $brands['rhye']);
