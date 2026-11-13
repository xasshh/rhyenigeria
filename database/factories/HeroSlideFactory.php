<?php

namespace Database\Factories;

use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlide>
 */
class HeroSlideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => fake()->numberBetween(1, 20),
            'is_active' => true,
            'layout' => 'image',
            'headline' => fake()->sentence(4),
            'body' => fake()->sentence(12),
            'button_label' => 'Join the Movement',
            'button_url' => '/join',
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}
