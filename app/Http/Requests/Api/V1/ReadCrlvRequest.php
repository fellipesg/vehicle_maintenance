<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\CrlvPdfFile;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Support\Facades\Gate;

class ReadCrlvRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\Vehicle::class);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'crlv' => ['required', 'file', new CrlvPdfFile, 'max:'.VehicleEntryFlow::DOCUMENT_MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'crlv.required' => 'Escolha o PDF do CRLV-e para continuar.',
            'crlv.max' => 'O CRLV-e pode ter no máximo 10 MB.',
        ];
    }
}
