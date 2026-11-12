<?php

namespace Database\Factories;

use App\Enums\AreaLevel;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->afterMaking(fn (User $user) => $user->role = $role);
    }

    public function superAdmin(): static
    {
        return $this->role(Role::SuperAdmin);
    }

    public function coordinator(?Model $area = null): static
    {
        return $this->role(Role::Coordinator)->inArea($area);
    }

    public function agent(?Model $area = null): static
    {
        return $this->role(Role::FieldAgent)->inArea($area);
    }

    /** A member living in the given area (a State, Lga or Ward model). */
    public function member(?Model $area = null): static
    {
        return $this->role(Role::Member)->inArea($area)->state(fn () => [
            'phone' => '+23480'.fake()->unique()->numerify('########'),
        ]);
    }

    /** Scope or assign the user to a State, Lga or Ward model. */
    public function inArea(?Model $area): static
    {
        if ($area === null) {
            return $this;
        }

        $level = AreaLevel::from(strtolower(class_basename($area)));

        return $this->afterMaking(fn (User $user) => $user->assignArea($level, $area->getKey()));
    }

    public function consented(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->tracking_consent_accepted_at = now()->subDay();
            $user->tracking_consent_version = '1';
        });
    }

    public function inactive(): static
    {
        return $this->afterMaking(fn (User $user) => $user->is_active = false);
    }
}
