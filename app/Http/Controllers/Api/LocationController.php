<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tracking\LocationIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Accept a batch of location points from the agent's own device.
     *
     * Always answers 200 once the batch is understood, even if some points
     * were dropped, so that a device never gets stuck re-sending a queue. The
     * counts in the response say what happened to each point.
     */
    public function store(Request $request, LocationIngestor $ingestor): JsonResponse
    {
        $agent = $request->user();

        abort_unless($agent->hasAcceptedTrackingConsent(), 403, 'Location tracking is not allowed until the tracking consent notice has been accepted.');

        $data = $request->validate([
            'points' => ['required', 'array', 'min:1', 'max:'.(int) config('tracking.max_batch_size')],
            'points.*.uuid' => ['required', 'uuid'],
            'points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'points.*.accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'points.*.recorded_at' => ['required', 'date'],
        ]);

        $result = $ingestor->ingest($agent, $data['points']);

        $hasActiveShift = $agent->activeShift()->exists();

        // Nothing in the batch was recorded during any of the agent's shifts
        // and none is running now: tracking is simply off.
        if (! $hasActiveShift && $result['matched_shift'] === 0) {
            return response()->json([
                'message' => 'No shift is in progress. Start a shift before sending locations.',
            ] + self::counts($result), 409);
        }

        return response()->json(self::counts($result) + ['shift_active' => $hasActiveShift]);
    }

    /**
     * @param  array<string, int>  $result
     * @return array<string, int>
     */
    private static function counts(array $result): array
    {
        return [
            'received' => $result['received'],
            'stored' => $result['stored'],
            'duplicates' => $result['duplicates'],
            'outside_shift' => $result['outside_shift'],
            'invalid_time' => $result['invalid_time'],
        ];
    }
}
