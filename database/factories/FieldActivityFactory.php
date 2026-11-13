<?php

namespace Database\Factories;

use App\Enums\FieldActivityKind;
use App\Models\FieldActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<FieldActivity>
 */
class FieldActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'kind' => FieldActivityKind::Report,
            'note' => fake()->sentence(),
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(3.35, 6.6), 4326)'),
            'accuracy' => 10,
            'recorded_at' => now(),
        ];
    }
}
