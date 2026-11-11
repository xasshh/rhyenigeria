<?php

use Illuminate\Support\Facades\Schedule;

// Retention sweep for location history (see config/tracking.php).
Schedule::command('tracking:prune')->dailyAt('03:15')->withoutOverlapping();

// Housekeeping for expired API tokens, if token expiry is ever configured.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
