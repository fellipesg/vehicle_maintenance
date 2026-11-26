<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Identity>
 */
class IdentityFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    /**
     * Identidade com 2FA ligado, com o segredo que o teste confere.
     */
    public function withTwoFactor(string $secret): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function forEmail(string $email): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => $email,
        ]);
    }
}
