<?php

namespace App\Http\Requests\Concerns;

use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use Illuminate\Validation\Validator;

trait ValidatesWarrantyTemplateImmutability
{
    protected function addWarrantyTemplateImmutabilityRules(Validator $validator, Workshop $workshop, ?WarrantyTemplate $template = null): void
    {
        $validator->after(function (Validator $validator) use ($workshop, $template): void {
            if ($template === null || ! $workshop->hasActiveWarranties()) {
                return;
            }

            $lockedFields = ['name', 'body', 'duration_days', 'scope'];
            $changed = [];

            foreach ($lockedFields as $field) {
                if (! $this->has($field)) {
                    continue;
                }

                $incoming = $this->input($field);

                // Só escalar entra na comparação. A guarda não é zelo: castar o que veio para
                // string fazia qualquer um dos campos travados chegando como array emitir
                // "Array to string conversion", que o HandleExceptions lança como ErrorException —
                // medido, o 422 virava 500 no PUT da web e no da API. O after() roda mesmo com a
                // regra do campo já reprovada, então não há nada antes dele que barre o array.
                // Valor inválido é problema da regra do campo, não da trava: aqui ele só não conta
                // como mudança, e os outros campos continuam sendo conferidos.
                if (! is_scalar($incoming)) {
                    continue;
                }

                $current = $template->{$field};

                if ($field === 'scope' && $current instanceof \BackedEnum) {
                    $current = $current->value;
                }

                if ((string) $incoming !== (string) $current) {
                    $changed[] = $field;
                }
            }

            if ($changed !== []) {
                $validator->errors()->add(
                    'name',
                    'Não é possível editar templates enquanto houver garantias vigentes desta oficina.',
                );
            }
        });
    }
}
