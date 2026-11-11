<?php

namespace Database\Factories;

use App\Models\PollingUnit;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PollingUnit> */
class PollingUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ward_id' => Ward::factory(),
            'code' => strtoupper(fake()->unique()->bothify('P###??')),
            'name' => fake()->company().' Primary School',
        ];
    }

    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn () => ['location' => PollingUnit::pointExpression($latitude, $longitude)]);
    }
}
