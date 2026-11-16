<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Services\Membership\Leaderboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * State rankings. Coordinators see every state's totals (aggregates only) with their own state
 * highlighted; the recruiter and canvasser tables stay inside their own state. Super admins see it all.
 */
class LeaderboardController extends Controller
{
    public function index(Request $request, Leaderboard $leaderboard): View
    {
        $viewer = $request->user();
        $metric = array_key_exists((string) $request->query('by'), Leaderboard::METRICS) ? (string) $request->query('by') : 'members';
        $states = $leaderboard->states($metric);

        $mine = null;
        $chasing = null;
        if ($viewer->isCoordinator() && $viewer->state_id) {
            $index = collect($states)->search(fn (array $row) => $row['id'] === $viewer->state_id);
            if ($index !== false) {
                $mine = $states[$index];
                // The nearest state ranked strictly above, and how far behind it this state is.
                $chasing = collect(array_slice($states, 0, $index))->reverse()->first(fn (array $row) => $row[$metric] > $mine[$metric]);
            }
        }

        return view('staff.leaderboard', [
            'metric' => $metric,
            'metrics' => Leaderboard::METRICS,
            'states' => $states,
            'mine' => $mine,
            'chasing' => $chasing,
            'leader' => $states[0] ?? null,
            'recruiters' => $leaderboard->recruiters($viewer),
            'canvassers' => $leaderboard->canvassers($viewer),
            'activityDays' => Leaderboard::ACTIVITY_DAYS,
        ]);
    }
}
