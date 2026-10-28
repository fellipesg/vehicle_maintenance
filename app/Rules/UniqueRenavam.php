<?php

namespace App\Rules;

use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RENAVAM único tratando o de 9 dígitos e o de 11 com "00" na frente como o mesmo número
 * (Vehicle::renavamVariants), para o mesmo veículo não entrar duas vezes.
 */
class UniqueRenavam implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreVehicleId = null,
        private readonly ?string $message = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            return;
        }

        $variants = Vehicle::renavamVariants((string) $value);

        if ($variants === []) {
            return;
        }

        $exists = Vehicle::query()
            ->whereIn('renavam', $variants)
            ->when($this->ignoreVehicleId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreVehicleId))
            ->exists();

        if ($exists) {
            $fail($this->message ?? 'Este RENAVAM já está cadastrado.');
        }
    }
}
