<?php

namespace Database\Factories;

use App\Enums\IncidentCategory;
use App\Enums\IncidentUrgency;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => IncidentCategory::Violence,
            'urgency' => IncidentUrgency::Serious,
            'description' => fake()->sentence(12),
        ];
    }
}
