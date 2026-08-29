<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\InvoiceFile;
use Illuminate\Support\Facades\Gate;

class UploadInvoiceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\Invoice::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', new InvoiceFile, 'max:10240'],
            'maintenance_id' => 'required|exists:maintenances,id',
            'maintenance_item_id' => 'nullable|exists:maintenance_items,id',
            'invoice_type' => 'required|in:item,general',
            'invoice_number' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'total_amount' => 'nullable|numeric|min:0',
        ];
    }
}
