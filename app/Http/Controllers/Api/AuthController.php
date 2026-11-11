<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
        }

        abort_unless($user->is_active, 403, 'This account has been deactivated.');

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'token_type' => 'Bearer',
            'user' => self::present($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => self::present($request->user())]);
    }

    /** @return array<string, mixed> */
    public static function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
            'area_level' => $user->areaLevel()?->value,
            'state_id' => $user->state_id,
            'lga_id' => $user->lga_id,
            'ward_id' => $user->ward_id,
            'tracking_consent_accepted_at' => $user->tracking_consent_accepted_at?->toIso8601ZuluString(),
            'tracking_consent_version' => $user->tracking_consent_version,
        ];
    }
}
