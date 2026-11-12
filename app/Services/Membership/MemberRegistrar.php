<?php

namespace App\Services\Membership;

use App\Enums\AreaLevel;
use App\Enums\CanvasserStatus;
use App\Enums\Role;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
            $profile->has_pvc = (bool) $data['has_pvc'];
            $profile->vin = $data['vin'] ?? null;
            $profile->nin = $data['nin'] ?? null;
            $profile->signup_lat = $data['latitude'] ?? null;
            $profile->signup_lng = $data['longitude'] ?? null;
            $profile->consent_accepted_at = now();
            $profile->consent_version = (string) config('membership.consent_version');

            if (filled($data['referrer'] ?? null)) {
                $profile->referred_by_user_id = MemberProfile::where('referral_code', $data['referrer'])->value('user_id');
            }

            if ($canvasser) {
                $profile->canvasser_status = CanvasserStatus::Pending;
                $profile->canvasser_roles = array_values($data['canvasser_roles']);
                $profile->whatsapp_phone = $data['whatsapp_phone'] ?? $data['phone'];
                $profile->canvasser_applied_at = now();
            }

            $profile->save();

            return $user->setRelation('memberProfile', $profile);
        });
    }
}
