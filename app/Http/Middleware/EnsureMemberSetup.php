<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Imported members must set their own password and finish their profile before using the dashboard. */
class EnsureMemberSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->needsSetup()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Finish setting up your account first.'], 409)
                : redirect()->route('membership.setup');
        }

        return $next($request);
    }
}
