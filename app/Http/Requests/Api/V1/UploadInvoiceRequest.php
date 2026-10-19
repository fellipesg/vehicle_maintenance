<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Rules\InvoiceFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UploadInvoiceRequest extends ApiFormRequest
{
    /**
     * A manutenção é autorizada aqui, e não só no controller, para que a OS de outro
     * tenant responda 403 antes de qualquer 422 sobre os outros campos (como em
     * LinkVehicleRequest). Manutenção inexistente segue para a validação, que devolve
     * o 422 de `maintenance_id`.
     */
    public function authorize(): bool
    {
        if (! Gate::allows('create', Invoice::class)) {
            return false;
        }

        $maintenance = $this->maintenance();

        return $maintenance === null || Gate::allows('update', $maintenance);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'max:10240', new InvoiceFile],
            'maintenance_id' => ['bail', 'required', 'integer', 'exists:maintenances,id'],
            // O item tem de ser da própria manutenção: sem o escopo, uma nota se anexa
            // ao item de outra OS. `bail` evita que o exists receba um id não numérico,
            // que no Postgres estoura 22P02 em vez de devolver 422.
            'maintenance_item_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('maintenance_items', 'id')
                    ->where('maintenance_id', $this->integerInput('maintenance_id')),
            ],
            'invoice_type' => ['required', 'in:item,general'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'maintenance_item_id.exists' => 'O item informado não pertence a esta manutenção.',
        ];
    }

    public function maintenance(): ?Maintenance
    {
        $maintenanceId = $this->integerInput('maintenance_id');

        return $maintenanceId === null ? null : Maintenance::find($maintenanceId);
    }

    /**
     * Only a whole number reaches the database; anything else stays null so the
     * field fails validation instead of comparing a string to a bigint column.
     */
    private function integerInput(string $key): ?int
    {
        $value = $this->input($key);

        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value
            : null;
    }
}
