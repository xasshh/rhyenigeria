<?php

namespace App\Http\Controllers\Web\Staff;

use App\Enums\CanvasserStatus;
use App\Http\Controllers\Controller;
use App\Models\State;
use App\Services\Audit\AuditLogger;
use App\Services\Membership\MemberDirectory;
use App\Services\Tracking\LiveOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where members pinned themselves (at sign-up or later from their dashboard). One fixed point per
 * member, never a trail. Scoped like every member list; loading the pins is audit-logged.
 */
class MemberMapController extends Controller
{
    /** Enough for any one state; the national view says when it was cut short. */
    public const MAX_PINS = 25_000;

    public function index(Request $request, MemberDirectory $directory, LiveOperations $live): View
    {
        $viewer = $request->user()->load('state');
        $stateId = $this->stateId($request);
        $members = $directory->query($viewer)->when($stateId, fn ($q) => $q->where('users.state_id', $stateId));

        return view('staff.members-map', [
            'stateId' => $stateId,
            'stateName' => LiveOperations::stateName($stateId),
            'states' => $viewer->isSuperAdmin() ? State::orderBy('name')->get(['id', 'name']) : collect(),
            'total' => (clone $members)->count(),
            'pinned' => (clone $members)->whereNotNull('mp.signup_lat')->whereNotNull('mp.signup_lng')->count(),
            'config' => [
                'pins' => staff_route('members.map.pins', array_filter(['state_id' => $viewer->isSuperAdmin() ? $stateId : null])),
                'member' => staff_route('members.show', '__ID__'),
                'outline' => $live->outline($stateId),
                'style' => config('tracking.map_style_url'),
            ],
        ]);
    }

    /** The pins as GeoJSON points: id, name, ward and whether the member is an approved canvasser. */
    public function pins(Request $request, MemberDirectory $directory, AuditLogger $audit): JsonResponse
    {
        $stateId = $this->stateId($request);

        $rows = $directory->query($request->user())
            ->when($stateId, fn ($q) => $q->where('users.state_id', $stateId))
            ->whereNotNull('mp.signup_lat')->whereNotNull('mp.signup_lng')
            ->leftJoin('wards', 'wards.id', '=', 'users.ward_id')
            ->leftJoin('lgas', 'lgas.id', '=', 'users.lga_id')
            ->orderByDesc('mp.location_pinned_at')
            ->limit(self::MAX_PINS + 1)
            ->toBase()
            ->get(['users.id', 'users.name', 'mp.signup_lat', 'mp.signup_lng', 'mp.canvasser_status', 'mp.location_pinned_at', 'wards.name as ward', 'lgas.name as lga']);

        $truncated = $rows->count() > self::MAX_PINS;
        $rows = $rows->take(self::MAX_PINS);
        $tz = config('membership.timezone');

        $audit->record($request, AuditLogger::MEMBERS_MAP_VIEWED, null, ['state_id' => $stateId, 'pins' => $rows->count()]);

        return response()->json([
            'type' => 'FeatureCollection',
            'truncated' => $truncated,
            'features' => $rows->map(fn (object $r) => [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [(float) $r->signup_lng, (float) $r->signup_lat]],
                'properties' => [
                    'id' => (int) $r->id,
                    'name' => $r->name,
                    'area' => collect([$r->ward, $r->lga])->filter()->implode(', '),
                    'canvasser' => $r->canvasser_status === CanvasserStatus::Approved->value,
                    'pinned' => $r->location_pinned_at ? now()->parse($r->location_pinned_at)->setTimezone($tz)->format('j M Y') : null,
                ],
            ])->values(),
        ])->setPrivate();
    }

    /** A coordinator's own state, always; a super admin's ?state_id, if any. */
    private function stateId(Request $request): ?int
    {
        $viewer = $request->user();

        return $viewer->isSuperAdmin() ? ($request->integer('state_id') ?: null) : $viewer->state_id;
    }
}
