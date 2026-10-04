<?php

namespace Database\Factories;

use App\Models\EmailSuppression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSuppression>
 */
class EmailSuppressionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'reason' => EmailSuppression::REASON_MANUAL,
        ];
    }
}
