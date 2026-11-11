<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\State;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;

/** Read-only lookups so clients can walk state > LGA > ward > polling unit. */
class GeographyController extends Controller
{
    public function states(): JsonResponse
    {
        return response()->json(['data' => State::query()->orderBy('name')->get(['id', 'code', 'name'])]);
    }

    public function lgas(State $state): JsonResponse
    {
        return response()->json(['data' => $state->lgas()->orderBy('name')->get(['id', 'state_id', 'code', 'name'])]);
    }

    public function wards(Lga $lga): JsonResponse
    {
        return response()->json(['data' => $lga->wards()->orderBy('name')->get(['id', 'lga_id', 'code', 'name'])]);
    }

    public function pollingUnits(Ward $ward): JsonResponse
    {
        $units = PollingUnit::query()
            ->where('ward_id', $ward->id)
            ->select(['id', 'ward_id', 'code', 'name'])
            ->withCoordinates()
            ->orderBy('code')
            ->get()
            ->map(fn (PollingUnit $unit) => [
                'id' => $unit->id,
                'ward_id' => $unit->ward_id,
                'code' => $unit->code,
                'name' => $unit->name,
                'latitude' => $unit->latitude === null ? null : (float) $unit->latitude,
                'longitude' => $unit->longitude === null ? null : (float) $unit->longitude,
            ]);

        return response()->json(['data' => $units]);
    }
}
