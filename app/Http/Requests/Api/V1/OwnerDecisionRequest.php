<?php

namespace App\Http\Requests\Api\V1;

class OwnerDecisionRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'link' => ['required', 'boolean'],
            'attach_files' => ['required', 'boolean'],
            'hide_from_public' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'link.required' => 'Informe se quer vincular o registro ao seu histórico.',
            'attach_files.required' => 'Informe se aceita as notas e fotos da oficina.',
            'hide_from_public.required' => 'Informe se quer ocultar o registro do histórico público.',
        ];
    }
}
