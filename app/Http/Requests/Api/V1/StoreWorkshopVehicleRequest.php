<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vehicle;

class StoreWorkshopVehicleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isWorkshop();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('chassis'))) {
            $this->merge(['chassis' => Vehicle::normalizeChassis((string) $this->input('chassis'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'chassis' => ['required', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chassis.size' => 'O chassi deve ter exatamente 17 caracteres.',
            'chassis.regex' => 'O chassi contém caracteres inválidos.',
            'brand.required' => 'Informe a marca do veículo.',
            'model.required' => 'Informe o modelo do veículo.',
            'year.required' => 'Informe o ano do veículo.',
        ];
    }
}
