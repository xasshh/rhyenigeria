<?php

namespace App\Support;

use App\Models\State;
use App\Models\User;

/**
 * Which state the command centre shows: for a super admin, the site's own state (or all of
 * Nigeria on the national site); for a state coordinator, their state.
 */
final class CommandScope
{
    public static function state(User $viewer): ?State
    {
        if ($viewer->isSuperAdmin()) {
            return site_state_code() ? State::where('code', site_state_code())->first() : null;
        }

        return $viewer->isStateCoordinator() ? $viewer->state : null;
    }
}
