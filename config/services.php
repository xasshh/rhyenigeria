<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Cloudflare Turnstile bot check on sign-up (free). Keys from the Cloudflare dashboard.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Bulk SMS for sign-in details (php artisan members:send-credentials). Sender IDs must be approved first.
    'bulksmsnigeria' => [
        'token' => env('BULKSMSNIGERIA_API_TOKEN'),
        'sender' => env('BULKSMSNIGERIA_SENDER_ID', 'RHYE'),
        // direct-refund (default), corporate/direct-corporate (transactional, reaches DND numbers) or otp.
        'gateway' => env('BULKSMSNIGERIA_GATEWAY', 'direct-corporate'),
        'url' => env('BULKSMSNIGERIA_URL', 'https://www.bulksmsnigeria.com/api/v2'),
    ],

    // Africa's Talking voice: the command centre's phone line. The number's callback URL in the AT
    // dashboard must be https://<site>/api/webhooks/voice/<AT_VOICE_WEBHOOK_TOKEN>.
    'africastalking' => [
        'username' => env('AT_USERNAME'),
        'api_key' => env('AT_API_KEY'),
        // The virtual number bought on AT, e.g. +2342017001234.
        'voice_number' => env('AT_VOICE_NUMBER'),
        // Phones that ring when someone calls the line (comma separated). Empty: callers leave a voice message.
        'forward_to' => env('AT_VOICE_FORWARD_TO'),
        'webhook_token' => env('AT_VOICE_WEBHOOK_TOKEN'),
        // Spoken to people who call the line.
        'greeting' => env('AT_VOICE_GREETING'),
    ],

];
