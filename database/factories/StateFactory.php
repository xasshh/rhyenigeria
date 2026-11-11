<?php

namespace Database\Factories;

use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<State> */
class StateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('S##??')),
            'name' => fake()->unique()->city().' State',
        ];
    }
}
