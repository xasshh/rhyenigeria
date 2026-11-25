<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A page that belongs to an optional feature is "not found" on a site that switches the feature off. */
class EnsureSiteFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(site_feature($feature), 404);

        return $next($request);
    }
}
