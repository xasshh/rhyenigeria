<?php

namespace Database\Factories;

use App\Models\XPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<XPost> */
class XPostFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $id = fake()->unique()->numberBetween(1_800_000_000_000_000_000, 1_990_000_000_000_000_000);

        return ['status_id' => $id, 'handle' => 'ubasanius', 'posted_at' => XPost::timeFromId($id)];
    }
}
