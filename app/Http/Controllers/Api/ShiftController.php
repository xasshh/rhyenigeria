<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shifts are always started and ended by the agent themselves; there is no
 * endpoint that lets anyone else switch an agent's tracking on.
 */
class ShiftController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $shift = $request->user()->activeShift()->first();

        return response()->json(['shift' => $shift ? self::present($shift) : null]);
    }

    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->hasAcceptedTrackingConsent(), 403, 'Accept the tracking consent notice before starting a shift.');

        if ($existing = $user->activeShift()->first()) {
            return response()->json(['message' => 'A shift is already in progress.', 'shift' => self::present($existing)], 409);
        }

        try {
            // In its own (nested) transaction so a lost race leaves the connection usable.
            $shift = DB::transaction(fn () => $user->shifts()->create(['started_at' => now()]));
        } catch (UniqueConstraintViolationException) {
            // Two start requests raced; the other one won.
            return response()->json(['message' => 'A shift is already in progress.', 'shift' => self::present($user->activeShift()->first())], 409);
        }

        return response()->json(['shift' => self::present($shift)], 201);
    }

    public function end(Request $request): JsonResponse
    {
        $shift = $request->user()->activeShift()->first();

        if (! $shift) {
            return response()->json(['message' => 'No shift is in progress.'], 409);
        }

        $shift->update(['ended_at' => now()]);

        return response()->json(['shift' => self::present($shift)]);
    }

    /** @return array<string, mixed> */
    public static function present(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'started_at' => $shift->started_at->toIso8601ZuluString(),
            'ended_at' => $shift->ended_at?->toIso8601ZuluString(),
            'active' => $shift->isActive(),
        ];
    }
}
