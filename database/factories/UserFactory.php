<?php

namespace Database\Factories;

use App\Models\Garage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'user_type' => 'user',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->tenant_id) {
                return;
            }

            $type = match ($user->user_type) {
                'garage' => 'garage',
                'workshop' => 'workshop',
                default => 'individual',
            };

            $tenant = Tenant::factory()->create([
                'type' => $type,
                'name' => $user->name,
            ]);

            $user->update(['tenant_id' => $tenant->id]);

            if ($type === 'garage') {
                Garage::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                ]);
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function asUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'user',
        ]);
    }

    public function asGarage(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'garage',
        ]);
    }

    public function asWorkshop(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'workshop',
        ]);
    }

    public function asAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
}
