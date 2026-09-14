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
