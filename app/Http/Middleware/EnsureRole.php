<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:super_admin,coordinator') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);
        abort_unless(in_array($user->role->value, $roles, true), 403, 'Your role is not allowed to do this.');

        return $next($request);
    }
}
