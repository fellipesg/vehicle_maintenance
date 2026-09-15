<?php

namespace App\Rules;

use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates vehicle chassis (VIN) per ISO 3779 for modern vehicles.
 *
 * Standard VIN: 17 alphanumeric characters excluding I, O, and Q.
 * Legacy chassis (9–17 characters, any alphanumeric) are accepted only when model year is before 1990.
 */
class Chassis implements ValidationRule
{
    public function __construct(private readonly ?int $year = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('O chassi deve ser informado.');

            return;
        }

        $normalized = Vehicle::normalizeChassis($value);

        if ($normalized === '') {
            $fail('O chassi deve ser informado.');

            return;
        }

        $year = $this->year;

        if ($year !== null && $year < 1990) {
            $length = strlen($normalized);
            if ($length < 9 || $length > 17) {
                $fail('Para veículos anteriores a 1990, o chassi deve ter entre 9 e 17 caracteres.');

                return;
            }

            if (! preg_match('/^[A-HJ-NPR-Z0-9]+$/', $normalized)) {
                $fail('O chassi contém caracteres inválidos.');

                return;
            }

            return;
        }

        if (strlen($normalized) !== 17) {
            $fail('O chassi deve ter exatamente 17 caracteres.');

            return;
        }

        if (! preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $normalized)) {
            $fail('O chassi deve ter 17 caracteres alfanuméricos (sem I, O ou Q).');

            return;
        }
    }
}
