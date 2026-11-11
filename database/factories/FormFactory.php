<?php

namespace Database\Factories;

use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Form> */
class FormFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->sentence(3),
            'fields' => null,
            'is_active' => true,
        ];
    }
}
