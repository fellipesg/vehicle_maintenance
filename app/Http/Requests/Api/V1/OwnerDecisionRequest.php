<?php

namespace App\Http\Requests\Api\V1;

class OwnerDecisionRequest extends ApiFormRequest
{
    /**
     * O proprietário não oculta mais o registro: no lugar do ocultar entrou a contestação
     * (POST /api/v1/maintenances/{id}/dispute), que marca sem tirar de circulação.
     */
    public const HIDE_REMOVED_MESSAGE = 'Ocultar o registro não é mais uma escolha do proprietário. Para dizer que este serviço não foi feito no seu carro, use "contestar": o registro continua visível, marcado como contestado por você, e a equipe analisa.';

    /**
     * `hide_from_public` continua sendo aceito (nullable) porque o app ainda o envia: tirá-lo do
     * contrato antes de o app parar de mandar faria a tela falhar na validação. Mandar `true`
     * responde 422 com HIDE_REMOVED_MESSAGE, em vez de "salvar" sem gravar nada em silêncio.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'link' => ['required', 'boolean'],
            'attach_files' => ['required', 'boolean'],
            'hide_from_public' => ['nullable', 'boolean'],
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
        ];
    }
}
