<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pages only a state coordinator (not an LGA or ward coordinator) may open. */
class EnsureStateCoordinator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isStateCoordinator(), 403);

        return $next($request);
    }
}
