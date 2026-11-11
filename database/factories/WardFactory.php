<?php

namespace Database\Factories;

use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ward> */
class WardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lga_id' => Lga::factory(),
            'code' => strtoupper(fake()->unique()->bothify('W##??')),
            'name' => fake()->streetName(),
        ];
    }
}
