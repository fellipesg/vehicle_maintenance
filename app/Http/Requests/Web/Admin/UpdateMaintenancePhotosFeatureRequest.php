<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Liga ou desliga as fotos das manutenções numa conta, oficina ou loja (App\Features\MaintenancePhotos).
 * Só administrador; a rota já passa pelo middleware admin, e a checagem aqui garante o mesmo se a
 * rota for reaproveitada.
 */
class UpdateMaintenancePhotosFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'enabled' => 'fotos das manutenções',
        ];
    }
}
