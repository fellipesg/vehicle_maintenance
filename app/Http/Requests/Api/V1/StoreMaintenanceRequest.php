<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\InvoiceFile;
use Illuminate\Support\Facades\Gate;

class StoreMaintenanceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\Maintenance::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => 'required|exists:vehicles,id',
            'workshop_id' => 'nullable|exists:workshops,id',
            'maintenance_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'workshop_name' => 'nullable|string|max:255',
            'maintenance_date' => 'required|date',
            'kilometers' => 'required|integer|min:0|max:9999999',
            'service_category' => 'required|in:mechanical,electrical,suspension,painting,finishing,interior,other',
            'is_manufacturer_required' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
            'items.*.part_number' => 'nullable|string|max:100',
            'invoices' => 'nullable|array',
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
            'checklists' => 'nullable|array',
            'checklists.*.checklist_type' => 'required_with:checklists|in:initial,final',
            'checklists.*.items' => 'required_with:checklists|array',
            'checklists.*.notes' => 'nullable|string',
        ];
    }
}
