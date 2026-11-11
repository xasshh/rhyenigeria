<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(self::present($request));
    }

    public function accept(Request $request): JsonResponse
    {
        $request->validate(['accepted' => ['required', 'accepted']]);

        $user = $request->user();
        $current = (string) config('tracking.consent_version');

        // Re-accepting the same version keeps the original acceptance time.
        if (! $user->hasAcceptedTrackingConsent() || $user->tracking_consent_version !== $current) {
            $user->tracking_consent_accepted_at = now();
            $user->tracking_consent_version = $current;
            $user->save();
        }

        return response()->json(self::present($request));
    }

    /** Withdrawing consent also ends any shift in progress, which stops tracking. */
    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->shifts()->whereNull('ended_at')->update(['ended_at' => now()]);
        $user->tracking_consent_accepted_at = null;
        $user->tracking_consent_version = null;
        $user->save();

        return response()->json(self::present($request));
    }

    /** @return array<string, mixed> */
    private static function present(Request $request): array
    {
        $user = $request->user();

        return [
            'accepted' => $user->hasAcceptedTrackingConsent(),
            'accepted_at' => $user->tracking_consent_accepted_at?->toIso8601ZuluString(),
            'accepted_version' => $user->tracking_consent_version,
            'current_version' => (string) config('tracking.consent_version'),
        ];
    }
}
