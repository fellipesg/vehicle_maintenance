<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vehicle;

class LookupWorkshopVehicleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isWorkshop();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('chassis'))) {
            $this->merge(['chassis' => Vehicle::normalizeChassis((string) $this->query('chassis'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'chassis' => ['required', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chassis.required' => 'Informe o chassi do veículo.',
            'chassis.size' => 'O chassi deve ter exatamente 17 caracteres.',
            'chassis.regex' => 'O chassi contém caracteres inválidos.',
        ];
    }
}
