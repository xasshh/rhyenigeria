<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\State;
use App\Services\Membership\MemberDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The state dashboard: a coordinator's own area, or any state for a super admin. */
class DashboardController extends Controller
{
    public function coordinator(Request $request, MemberDirectory $directory): View
    {
        $viewer = $request->user()->load('state', 'lga', 'ward');

        return $this->render($directory, $directory->query($viewer), $viewer->ward?->name ?? $viewer->lga?->name ?? $viewer->state?->name, $viewer->state_id === null);
    }

    public function state(Request $request, State $state, MemberDirectory $directory): View
    {
        $members = $directory->query($request->user())->where('users.state_id', $state->id);

        return $this->render($directory, $members, $state->name, false, $state);
    }

    private function render(MemberDirectory $directory, $members, ?string $areaName, bool $unassigned, ?State $state = null): View
    {
        return view('staff.dashboard', [
            'areaName' => $areaName,
            'unassigned' => $unassigned,
            'state' => $state,
            'summary' => $directory->summary($members),
            'byLga' => $directory->byArea($members, 'lga'),
            'byWard' => $directory->byArea($members, 'ward')->take(50),
            'gender' => $directory->breakdown($members, 'gender'),
            'ages' => $directory->breakdown($members, 'age_range'),
            'trend' => $directory->perDay($members, 30),
        ]);
    }
}
