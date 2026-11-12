<?php

namespace Database\Factories;

use App\Enums\AgeRange;
use App\Enums\CanvasserRole;
use App\Enums\CanvasserStatus;
use App\Enums\Gender;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MemberProfile> */
class MemberProfileFactory extends Factory
{
    public function definition(): array
    {
        $number = 'RHYE-T-'.fake()->unique()->numerify('######');

        return [
            'user_id' => User::factory()->member(),
            'member_number' => $number,
            'verify_token' => Str::random(40),
            'gender' => fake()->randomElement(Gender::cases()),
            'age_range' => fake()->randomElement(AgeRange::cases()),
            'occupation' => fake()->jobTitle(),
            'has_pvc' => false,
            'referral_code' => $number,
            'consent_accepted_at' => now(),
            'consent_version' => '1',
        ];
    }

    public function withPvc(?string $vin = '90F5B12345678901234'): static
    {
        return $this->state(['has_pvc' => true, 'vin' => $vin]);
    }

    public function canvasser(CanvasserStatus $status = CanvasserStatus::Pending): static
    {
        return $this->state([
            'canvasser_status' => $status,
            'canvasser_roles' => [CanvasserRole::DoorToDoor->value],
            'canvasser_applied_at' => now(),
        ]);
    }
}
