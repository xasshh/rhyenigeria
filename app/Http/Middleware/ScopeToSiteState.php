<?php

namespace App\Http\Middleware;

use App\Models\State;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On a single-state site (e.g. Kaduna only) a super admin's members, maps, field team and
 * applications pages always show that state: the "state" filter is fixed to it, whatever was asked.
 */
class ScopeToSiteState
{
    public function handle(Request $request, Closure $next): Response
    {
        if (site_state_code() && $request->user()?->isSuperAdmin()) {
            $stateId = State::where('code', site_state_code())->value('id');
            if ($stateId) {
                $request->query->set('state_id', (string) $stateId);
                $request->request->set('state_id', (string) $stateId);
                $request->merge(['state_id' => (string) $stateId]);
            }
        }

        return $next($request);
    }
}
