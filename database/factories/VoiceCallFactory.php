<?php

namespace Database\Factories;

use App\Models\VoiceCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VoiceCall> */
class VoiceCallFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'session_id' => 'ATVId_'.fake()->unique()->bothify('????########'),
            'direction' => VoiceCall::INBOUND,
            'status' => 'missed',
            'contact_phone' => '+23480'.fake()->unique()->numerify('########'),
        ];
    }
}
