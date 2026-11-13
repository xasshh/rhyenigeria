<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\State;
use App\Services\Geo\AreaMap;
use App\Services\Membership\MemberDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The state dashboard: a coordinator's own area, or any state for a super admin. */
class DashboardController extends Controller
{
    public function coordinator(Request $request, MemberDirectory $directory, AreaMap $map): View
    {
        $viewer = $request->user()->load('state', 'lga', 'ward');

        return $this->render($directory, $directory->query($viewer), $viewer->ward?->name ?? $viewer->lga?->name ?? $viewer->state?->name, $viewer->state_id === null, null, $viewer->state ? $map->lgas($viewer->state) : null);
    }

    public function state(Request $request, State $state, MemberDirectory $directory, AreaMap $map): View
    {
        $members = $directory->query($request->user())->where('users.state_id', $state->id);

        return $this->render($directory, $members, $state->name, false, $state, $map->lgas($state));
    }

    /**
     * @param  array{viewBox: string, areas: list<array<string, mixed>>}|null  $lgaMap
     */
    private function render(MemberDirectory $directory, Builder $members, ?string $areaName, bool $unassigned, ?State $state = null, ?array $lgaMap = null): View
    {
        return view('staff.dashboard', [
            'lgaMap' => $lgaMap,
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
