<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\State;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tracking\LiveOperations;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The live map: canvassers on shift, their routes and what they logged today.
 * Coordinators see their own state only; super admins pick a state or see all.
 */
class LiveMapController extends Controller
{
    public function index(Request $request, LiveOperations $live): View
    {
        $viewer = $request->user()->load('state');
        $stateId = $this->stateId($request);

        return view('staff.live', [
            'stateId' => $stateId,
            'stateName' => LiveOperations::stateName($stateId),
            'states' => $viewer->isSuperAdmin() ? State::orderBy('name')->get(['id', 'name']) : collect(),
            'config' => [
                'feed' => staff_route('live.feed', array_filter(['state_id' => $viewer->isSuperAdmin() ? $stateId : null])),
                'trail' => staff_route('live.trail', '__ID__'),
                'member' => staff_route('members.show', '__ID__'),
                'outline' => $live->outline($stateId),
                'style' => config('tracking.map_style_url'),
                'refreshSeconds' => 20,
                'activeMinutes' => LiveOperations::ACTIVE_MINUTES,
            ],
        ]);
    }

    public function feed(Request $request, LiveOperations $live): JsonResponse
    {
        return response()->json($live->feed($request->user(), $this->stateId($request)))->setPrivate();
    }

    /** One canvasser's route today (or ?date=). 404 outside the viewer's area; every view is audit-logged. */
    public function trail(Request $request, User $member, LiveOperations $live, AuditLogger $audit): JsonResponse
    {
        abort_unless(User::query()->canvassersVisibleTo($request->user())->whereKey($member->id)->exists(), 404);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $day = isset($data['date']) ? CarbonImmutable::parse($data['date'], config('membership.timezone')) : null;

        $trail = $live->trail($member, $day);
        $audit->record($request, AuditLogger::TRAIL_VIEWED, $member, [
            'date' => ($day ?? CarbonImmutable::now(config('membership.timezone')))->format('Y-m-d'),
            'points' => $trail['features'][0]['properties']['points'] ?? 0,
        ]);

        return response()->json($trail)->setPrivate();
    }

    /** A coordinator's own state, always; a super admin's ?state_id, if any. */
    private function stateId(Request $request): ?int
    {
        $viewer = $request->user();

        return $viewer->isSuperAdmin() ? ($request->integer('state_id') ?: null) : $viewer->state_id;
    }
}
