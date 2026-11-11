<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentPosition;
use App\Models\LocationPoint;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /** Latest position of every agent the requester may see, as GeoJSON. */
    public function positions(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $data = $request->validate(['on_shift' => ['nullable', 'boolean']]);

        $positions = AgentPosition::query()
            ->withCoordinates()
            ->whereHas('user', function ($query) use ($viewer, $data) {
                $query->visibleTo($viewer)->fieldAgents();

                if (! empty($data['on_shift'])) {
                    $query->whereHas('activeShift');
                }
            })
            ->with(['user.activeShift', 'ward', 'nearestPollingUnit'])
            ->orderBy('user_id')
            ->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $positions->map(function (AgentPosition $position) {
                $properties = $position->toMapArray();
                unset($properties['latitude'], $properties['longitude']);
                $properties['on_shift'] = $position->user->activeShift !== null;

                return [
                    'type' => 'Feature',
                    'id' => $position->user_id,
                    'geometry' => ['type' => 'Point', 'coordinates' => [$position->longitude, $position->latitude]],
                    'properties' => $properties,
                ];
            })->all(),
        ]);
    }

    /** One agent's trail for a time window, as GeoJSON. Every view is audit-logged. */
    public function trail(Request $request, User $agent, AuditLogger $audit): JsonResponse
    {
        abort_unless($agent->isFieldAgent(), 404);
        abort_unless($request->user()->canSee($agent), 403, 'This agent is outside your area.');

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'points' => ['nullable', 'boolean'],
        ]);

        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->utc() : CarbonImmutable::now('UTC');
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->utc() : $to->subDay();

        abort_if($from->greaterThan($to), 422, 'The "from" time must be before the "to" time.');
        $maxDays = (int) config('tracking.max_trail_window_days');
        abort_if($from->diffInSeconds($to) > $maxDays * 86400, 422, "A trail request may cover at most {$maxDays} days.");

        $points = LocationPoint::query()
            ->withCoordinates()
            ->where('user_id', $agent->id)
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        $audit->record($request, AuditLogger::TRAIL_VIEWED, $agent, [
            'from' => $from->toIso8601ZuluString(),
            'to' => $to->toIso8601ZuluString(),
            'points' => $points->count(),
        ]);

        $features = [];

        // The path itself, in the order the points were recorded.
        if ($points->count() >= 2) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $points->map(fn (LocationPoint $p) => [$p->longitude, $p->latitude])->all(),
                ],
                'properties' => [
                    'kind' => 'trail',
                    'agent_id' => $agent->id,
                    'started_at' => $points->first()->recorded_at->toIso8601ZuluString(),
                    'ended_at' => $points->last()->recorded_at->toIso8601ZuluString(),
                ],
            ];
        }

        if ($data['points'] ?? true) {
            foreach ($points as $point) {
                $features[] = [
                    'type' => 'Feature',
                    'id' => $point->uuid,
                    'geometry' => ['type' => 'Point', 'coordinates' => [$point->longitude, $point->latitude]],
                    'properties' => [
                        'kind' => 'point',
                        'recorded_at' => $point->recorded_at->toIso8601ZuluString(),
                        'accuracy' => $point->accuracy,
                        'shift_id' => $point->shift_id,
                        'ward_id' => $point->ward_id,
                        'nearest_polling_unit_id' => $point->nearest_polling_unit_id,
                        'nearest_polling_unit_distance_m' => $point->nearest_polling_unit_distance_m === null ? null : round($point->nearest_polling_unit_distance_m, 1),
                    ],
                ];
            }
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => [
                'agent' => ['id' => $agent->id, 'name' => $agent->name],
                'from' => $from->toIso8601ZuluString(),
                'to' => $to->toIso8601ZuluString(),
                'point_count' => $points->count(),
            ],
        ]);
    }
}
