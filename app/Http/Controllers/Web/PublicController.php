<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use App\Models\State;
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

    /**
     * State WhatsApp groups. Anyone can pick a state and see its coordinator; the invite link itself is only
     * shown to signed-in members of that state (on this page and their dashboard), so people register first.
     */
    public function whatsapp(Request $request): View
    {
        $user = $request->user()?->loadMissing('state');
        $states = State::orderBy('name')->get(['id', 'code', 'name', 'coordinator_name', 'coordinator_photo_path', 'whatsapp_group_url']);
        $selected = $states->firstWhere('code', strtoupper((string) $request->query('state')))
            ?? ($user?->isMember() ? $states->firstWhere('id', $user->state_id) : null);

        return view('whatsapp', [
            'states' => $states->map(fn (State $s) => [
                'code' => $s->code,
                'name' => $s->name,
                'coordinator' => $s->coordinator_name,
                'photo' => $s->coordinatorPhotoUrl(),
                'hasGroup' => filled($s->whatsapp_group_url),
                'join' => route('join', ['state' => $s->code]),
            ])->values(),
            'selected' => $selected?->code,
            'member' => $user?->isMember() ? $user : null,
            'memberGroup' => $user?->isMember() ? $user->state?->whatsapp_group_url : null,
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
