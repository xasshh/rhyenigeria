<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Staff given a temporary password (by a coordinator or admin) choose their own before anything else. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Choose your own password first.'], 409)
                : redirect()->route('account.password');
        }

        return $next($request);
    }
}
