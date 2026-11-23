<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Location batches: limited per agent, not per IP, because many agents
        // can share one mobile network address.
        RateLimiter::for('locations', fn (Request $request) => Limit::perMinute((int) config('tracking.rate_limit_per_minute'))
            ->by('locations:'.($request->user()?->id ?: $request->ip())));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)
            ->by('login:'.strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Public sign-up. Per IP, but generous: mobile networks share addresses.
        RateLimiter::for('join', fn (Request $request) => Limit::perMinute((int) config('membership.join_rate_limit_per_minute'))
            ->by('join:'.$request->ip()));

        RateLimiter::for('join-check', fn (Request $request) => Limit::perMinute((int) config('membership.join_check_rate_limit_per_minute'))->by('join-check:'.$request->ip()));

        RateLimiter::for('field-register', fn (Request $request) => Limit::perHour((int) config('membership.field_registrations_per_hour'))
            ->by('field-register:'.$request->user()?->id)
            ->response(fn () => response()->json(['message' => 'You have registered a lot of people in the last hour. Please wait a little before adding more.'], 429)));

        // Incident reports and updates: plenty for a busy election day, not enough to flood the desk.
        RateLimiter::for('incidents', fn (Request $request) => Limit::perHour(40)->by('incidents:'.$request->user()?->id));

        RateLimiter::for('geo', fn (Request $request) => Limit::perMinute((int) config('membership.geo_rate_limit_per_minute'))->by('geo:'.$request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by('reset:'.$request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
