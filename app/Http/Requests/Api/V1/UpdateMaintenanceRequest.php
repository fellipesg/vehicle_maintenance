<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Gate;

class UpdateMaintenanceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $maintenance = \App\Models\Maintenance::find($this->route('id'));

        return $maintenance !== null && Gate::allows('update', $maintenance);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workshop_id' => 'nullable|exists:workshops,id',
            'maintenance_type' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'workshop_name' => 'nullable|string|max:255',
            'maintenance_date' => 'sometimes|required|date',
            'kilometers' => 'sometimes|required|integer|min:0|max:9999999',
            'service_category' => 'sometimes|required|in:mechanical,electrical,suspension,painting,finishing,interior,other',
            'is_manufacturer_required' => 'boolean',
        ];
    }
}
