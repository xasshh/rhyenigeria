<?php

return [

    'photo_disk' => env('SUBMISSION_PHOTO_DISK', 'local'),

    'photo_max_kb' => (int) env('SUBMISSION_PHOTO_MAX_KB', 8192),

    'max_photos' => (int) env('SUBMISSION_MAX_PHOTOS', 5),

    // Hard cap on rows written by one CSV export.
    'export_max_rows' => 50000,

];
