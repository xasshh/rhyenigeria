<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicController extends Controller
{
    /**
     * Where a membership card's QR code leads. Confirms the person is an active
     * member and shows nothing private: no phone, email, NIN, VIN or address.
     */
    public function verify(string $token): View
    {
        $profile = strlen($token) <= 64
            ? MemberProfile::with('user.state')->where('verify_token', $token)->first()
            : null;

        $valid = $profile !== null && $profile->user?->is_active && $profile->user->isMember();

        return view('verify', [
            'valid' => $valid,
            'name' => $valid ? $profile->user->publicName() : null,
            'state' => $valid ? $profile->user->state?->name : null,
            'since' => $valid ? $profile->created_at : null,
        ]);
    }

    public function privacy(): View
    {
        return view('privacy');
    }

    /** The homepage "Empowerment" button: each role's Empowerment page. Guests sign in first and land here. */
    public function empowerment(Request $request): RedirectResponse
    {
        $user = $request->user();

        return redirect()->route(match (true) {
            $user->isSuperAdmin() => 'admin.empowerment.index',
            $user->isCoordinator() => 'coordinator.empowerment.index',
            default => 'membership.empowerment',
        });
    }
}
