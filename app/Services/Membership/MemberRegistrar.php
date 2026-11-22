<?php

namespace App\Services\Membership;

use App\Enums\AreaLevel;
use App\Enums\CanvasserStatus;
use App\Enums\Role;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MemberRegistrar
{
    /**
     * Create a member (and their profile) from validated sign-up data.
     *
     * @param  array<string, mixed>  $data  Output of JoinForm::validate().
     */
    public function register(array $data, bool $canvasser): User
    {
        return DB::transaction(function () use ($data, $canvasser) {
            $user = new User([
                'name' => preg_replace('/\s+/', ' ', $data['name']),
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);
            // Always explicit: the column default is `field_agent`.
            $user->role = Role::Member;
            $user->is_active = true;
            $user->assignArea(
                isset($data['ward_id']) ? AreaLevel::Ward : AreaLevel::Lga,
                (int) ($data['ward_id'] ?? $data['lga_id']),
            );
            $user->save();

            $state = State::findOrFail($user->state_id);
            $number = MemberProfile::newMemberNumber($state);

            $profile = new MemberProfile;
            $profile->user_id = $user->id;
            $profile->member_number = $number;
            $profile->referral_code = $number;
            $profile->verify_token = MemberProfile::newVerifyToken();
            $profile->gender = $data['gender'];
            $profile->age_range = $data['age_range'];
            $profile->occupation = $data['occupation'] ?? null;
            $profile->polling_unit_id = $data['polling_unit_id'] ?? null;
            $profile->polling_unit_name = $profile->polling_unit_id ? null : ($data['polling_unit_name'] ?? null);
            $profile->has_pvc = (bool) $data['has_pvc'];
            $profile->vin = $data['vin'] ?? null;
            $profile->nin = $data['nin'] ?? null;
            $profile->signup_lat = $data['latitude'] ?? null;
            $profile->signup_lng = $data['longitude'] ?? null;
            $profile->location_pinned_at = isset($data['latitude'], $data['longitude']) ? now() : null;
            $profile->consent_accepted_at = now();
            $profile->consent_version = (string) config('membership.consent_version');

            if (filled($data['referrer'] ?? null)) {
                $profile->referred_by_user_id = MemberProfile::where('referral_code', $data['referrer'])->value('user_id');
            }

            if ($canvasser) {
                $profile->canvasser_status = CanvasserStatus::Pending;
                $profile->canvasser_roles = config('site.canvasser_roles') ?? array_values($data['canvasser_roles']);
                $profile->whatsapp_phone = $data['whatsapp_phone'] ?? $data['phone'];
                $profile->canvasser_applied_at = now();
            }

            $profile->save();

            return $user->setRelation('memberProfile', $profile);
        });
    }

    /**
     * A member signed up in person by a canvasser on shift, usually someone without a smartphone.
     * They have no email and no password yet (they get one by SMS later). The canvasser read them the
     * data notice and records that they agreed, so the canvasser is kept as the witness.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerInField(User $canvasser, array $data): User
    {
        return DB::transaction(function () use ($canvasser, $data) {
            $user = new User(['name' => preg_replace('/\s+/', ' ', trim($data['name'])), 'phone' => $data['phone']]);
            // Nobody knows this password; the member gets their own by SMS (members:send-credentials).
            $user->password = Hash::make(Str::random(64));
            $user->role = Role::Member;
            $user->is_active = true;
            $user->must_change_password = true;
            $user->assignArea(
                isset($data['ward_id']) ? AreaLevel::Ward : AreaLevel::Lga,
                (int) ($data['ward_id'] ?? $data['lga_id']),
            );
            $user->save();

            $number = MemberProfile::newMemberNumber(State::findOrFail($user->state_id));

            $profile = new MemberProfile;
            $profile->user_id = $user->id;
            $profile->member_number = $number;
            $profile->referral_code = $number;
            $profile->verify_token = MemberProfile::newVerifyToken();
            $profile->gender = $data['gender'];
            $profile->age_range = $data['age_range'];
            $profile->polling_unit_id = $data['polling_unit_id'] ?? null;
            $profile->polling_unit_name = $profile->polling_unit_id ? null : ($data['polling_unit_name'] ?? null);
            $profile->has_pvc = (bool) $data['has_pvc'];
            $profile->signup_lat = $data['latitude'];
            $profile->signup_lng = $data['longitude'];
            $profile->location_pinned_at = now();
            $profile->consent_accepted_at = now();
            $profile->consent_version = (string) config('membership.consent_version');
            $profile->referred_by_user_id = $canvasser->id;
            $profile->registered_by_user_id = $canvasser->id;
            $profile->save();

            return $user->setRelation('memberProfile', $profile);
        });
    }
}
