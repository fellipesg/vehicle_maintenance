<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ServiceCategory;
use App\Models\Maintenance;
use App\Rules\InvoiceFile;
use App\Rules\RequiresInvoiceWhenWorkshopAssigned;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $maintenance = Maintenance::find($this->route('id'));

        return $maintenance !== null && Gate::allows('update', $maintenance);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maintenance = Maintenance::find($this->route('id'));
        $workshopId = $this->has('workshop_id')
            ? ($this->input('workshop_id') !== null ? (int) $this->input('workshop_id') : null)
            : $maintenance?->workshop_id;

        return [
            'workshop_id' => array_values(array_filter([
                'nullable',
                'exists:workshops,id',
                $maintenance?->rejected_workshop_id !== null ? Rule::notIn([$maintenance->rejected_workshop_id]) : null,
            ])),
            'maintenance_type' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'workshop_name' => 'nullable|string|max:255',
            'maintenance_date' => 'sometimes|required|date',
            'kilometers' => 'sometimes|required|integer|min:0|max:9999999',
            'service_category' => ['sometimes', 'required', Rule::in(ServiceCategory::values())],
            'is_manufacturer_required' => 'boolean',

            // Sem estas regras os itens enviados pelo app caíam fora do
            // validated() e a edição respondia 200 sem ter gravado nada.
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.warranty_template_id' => 'nullable|integer|exists:warranty_templates,id',
            'general_warranty_template_id' => 'nullable|integer|exists:warranty_templates,id',

            'invoices' => [
                'nullable',
                'array',
                new RequiresInvoiceWhenWorkshopAssigned(
                    workshopId: $workshopId,
                    isWorkshopPortal: (bool) $this->user()?->isWorkshop(),
                    existingInvoiceCount: $maintenance?->invoices()->count() ?? 0,
                ),
            ],
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
        ];
    }
}
