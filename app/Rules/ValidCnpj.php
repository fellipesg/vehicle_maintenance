<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CNPJ com 14 dígitos e dígitos verificadores corretos. Espera o valor já sem pontuação.
 */
class ValidCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('Informe um CNPJ válido, com 14 dígitos.');
        }
    }

    public static function isValid(string $cnpj): bool
    {
        $digits = preg_replace('/\D/', '', $cnpj) ?? '';

        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        foreach ([12, 13] as $length) {
            if ((int) $digits[$length] !== self::checkDigit(substr($digits, 0, $length))) {
                return false;
            }
        }

        return true;
    }

    private static function checkDigit(string $base): int
    {
        $weight = strlen($base) - 7;
        $sum = 0;

        foreach (str_split($base) as $digit) {
            $sum += (int) $digit * $weight;
            $weight = $weight === 2 ? 9 : $weight - 1;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
